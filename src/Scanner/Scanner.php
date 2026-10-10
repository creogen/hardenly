<?php declare( strict_types=1 );

namespace Sitewarden\Scanner;

use InvalidArgumentException;
use Merkushin\Wpal\Service\Cron;
use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Storage\Lock;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Storage\RunStore;
use Sitewarden\Storage\Settings;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Runs scans in batches.
 *
 * start() creates a run and schedules a WP-Cron event. Each event (or a tick from the admin
 * screen while it is open) runs checks until the time budget is spent, saves where it stopped
 * and schedules the next batch. The last batch stores the report. Only one batch runs at a
 * time, guarded by a lock.
 */
class Scanner {
	public const TICK_HOOK = 'sitewarden_scan_tick';

	public const STATE_IDLE    = 'idle';
	public const STATE_QUEUED  = 'queued';
	public const STATE_RUNNING = 'running';
	public const STATE_FAILED  = 'failed';
	public const STATE_STOPPED = 'stopped';

	/**
	 * Default seconds of work per batch.
	 */
	public const TIME_BUDGET = 10.0;

	/**
	 * A run without progress for this long is marked failed.
	 */
	public const STALL_SECONDS = 900;

	/**
	 * Results kept per check.
	 */
	public const MAX_RESULTS_PER_CHECK = 500;

	/**
	 * @var CheckRegistry
	 */
	private $registry;

	/**
	 * @var RunStore
	 */
	private $runs;

	/**
	 * @var ReportStore
	 */
	private $reports;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Lock
	 */
	private $lock;

	/**
	 * @var Score
	 */
	private $score;

	/**
	 * @var callable(): float
	 */
	private $clock;

	/**
	 * @var Cron
	 */
	private $cron;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Environment
	 */
	private $environment;

	/**
	 * @param CheckRegistry $registry Checks to run.
	 * @param RunStore      $runs     State of the current run.
	 * @param ReportStore   $reports  Finished reports.
	 * @param Settings      $settings Plugin settings.
	 * @param Lock          $lock     Keeps batches from overlapping.
	 * @param Score         $score    Score calculation.
	 * @param callable|null $clock    Returns the time in seconds; microtime() by default.
	 */
	public function __construct(
		CheckRegistry $registry,
		RunStore $runs,
		ReportStore $reports,
		Settings $settings,
		Lock $lock,
		Score $score,
		?callable $clock = null
	) {
		$this->registry    = $registry;
		$this->runs        = $runs;
		$this->reports     = $reports;
		$this->settings    = $settings;
		$this->lock        = $lock;
		$this->score       = $score;
		$this->clock       = $clock ?? static function (): float {
			return microtime( true );
		};
		$this->cron        = ServiceFactory::create_cron();
		$this->hooks       = ServiceFactory::create_hooks();
		$this->environment = ServiceFactory::create_environment();
	}

	/**
	 * Queues a new scan.
	 *
	 * @param string $trigger What started it: `manual` or `schedule`.
	 *
	 * @return bool False when a scan is already running.
	 */
	public function start( string $trigger = 'manual' ): bool {
		$state = $this->current_state();
		if ( null !== $state && self::is_active( $state ) ) {
			return false;
		}

		$now      = $this->now();
		$settings = $this->settings->get();
		$disabled = is_array( $settings['disabled_groups'] ) ? $settings['disabled_groups'] : [];

		$this->runs->save(
			[
				'id'         => bin2hex( random_bytes( 8 ) ),
				'status'     => self::STATE_QUEUED,
				'trigger'    => $trigger,
				'started_at' => $now,
				'updated_at' => $now,
				'checks'     => $this->registry->enabled_ids( $disabled ),
				'position'   => 0,
				'cursor'     => null,
				'results'    => [],
			]
		);
		$this->schedule_next();
		$this->cron->spawn_cron();

		return true;
	}

	/**
	 * Stops the running scan. Results found so far are discarded.
	 */
	public function stop(): void {
		$state = $this->current_state();
		if ( null === $state || ! self::is_active( $state ) ) {
			return;
		}

		$state['status']     = self::STATE_STOPPED;
		$state['updated_at'] = $this->now();
		$state['results']    = [];
		$this->runs->save( $state );
		$this->cron->wp_clear_scheduled_hook( self::TICK_HOOK, [ $state['id'] ] );
	}

	/**
	 * Handles the WP-Cron event: runs a batch of the given run.
	 *
	 * @param mixed $run_id Run the event was scheduled for.
	 */
	public function handle_tick_event( $run_id = '' ): void {
		$state = $this->runs->get();
		if ( null !== $state && ( $state['id'] ?? '' ) === $run_id ) {
			$this->tick();
		}
	}

	/**
	 * Runs one batch of the current scan, if there is one and no other batch is running.
	 *
	 * @param float $budget Seconds of work; the batch always runs at least one check call.
	 */
	public function tick( float $budget = self::TIME_BUDGET ): void {
		$state = $this->current_state();
		if ( null === $state || ! self::is_active( $state ) ) {
			return;
		}
		if ( ! $this->lock->acquire( (int) ceil( $budget ) + 60 ) ) {
			return;
		}

		try {
			$this->run_batch( $budget );
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Public view of the current run for the admin screen.
	 *
	 * @return array{state: string, run_id: string, started_at: int, progress: array{done: int, total: int, percent: int, current: array{id: string, title: string, group: string}|null}}
	 */
	public function status(): array {
		$state = $this->current_state();
		if ( null === $state ) {
			return [
				'state'      => self::STATE_IDLE,
				'run_id'     => '',
				'started_at' => 0,
				'progress'   => [
					'done'    => 0,
					'total'   => 0,
					'percent' => 0,
					'current' => null,
				],
			];
		}

		$ids     = $state['checks'];
		$total   = count( $ids );
		$done    = min( (int) $state['position'], $total );
		$current = null;
		if ( $done < $total ) {
			$check = $this->registry->get( $ids[ $done ] );
			if ( null !== $check ) {
				$current = [
					'id'    => $check->id(),
					'title' => $check->title(),
					'group' => $check->group(),
				];
			}
		}

		return [
			'state'      => (string) $state['status'],
			'run_id'     => (string) $state['id'],
			'started_at' => (int) $state['started_at'],
			'progress'   => [
				'done'    => $done,
				'total'   => $total,
				'percent' => $total > 0 ? (int) floor( $done * 100 / $total ) : 0,
				'current' => $current,
			],
		];
	}

	private function run_batch( float $budget ): void {
		$state = $this->current_state();
		if ( null === $state || ! self::is_active( $state ) ) {
			return;
		}

		$state['status'] = self::STATE_RUNNING;
		$settings        = $this->settings->get();
		$deadline        = ( $this->clock )() + $budget;
		$memory_limit    = $this->memory_limit();
		$ids             = $state['checks'];
		$count           = count( $ids );
		$ran             = false;

		while ( $state['position'] < $count ) {
			$context = new Context( $settings, $state['cursor'], $deadline, $memory_limit, $this->clock );
			if ( $ran && $context->should_yield() ) {
				break;
			}

			$id    = $ids[ $state['position'] ];
			$check = $this->registry->get( $id );
			if ( null === $check ) {
				// Removed through the filter since the run started.
				++$state['position'];
				$state['cursor'] = null;
				continue;
			}

			$failed  = false;
			$results = $this->run_check( $check, $context, $failed );
			$ran     = true;

			$state['results'] = array_merge( $state['results'], $this->limit_results( $id, $results, $state['results'] ) );
			if ( $context->is_paused() && ! $failed ) {
				$state['cursor'] = $context->next_cursor();
			} else {
				++$state['position'];
				$state['cursor'] = null;
			}
		}

		// The run may have been stopped while this batch worked.
		$latest = $this->runs->get();
		if ( null === $latest || ( $latest['id'] ?? '' ) !== $state['id'] || ! self::is_active( $latest ) ) {
			return;
		}

		$state['updated_at'] = $this->now();
		if ( $state['position'] >= $count ) {
			$this->complete( $state );
			return;
		}

		$this->runs->save( $state );
		$this->schedule_next();
	}

	/**
	 * @param Check   $check   Check to run.
	 * @param Context $context Its context.
	 * @param bool    $failed  Set to true when the check threw.
	 *
	 * @return array<int, array<string, mixed>> Results as arrays.
	 */
	private function run_check( Check $check, Context $context, bool &$failed ): array {
		try {
			$results = $check->run( $context );
		} catch ( Throwable $e ) {
			// A failing check counts as done, even if it paused before throwing.
			$failed = true;
			return [
				Result::skipped(
					$check->id(),
					/* translators: %s: error message. */
					sprintf( __( 'This check could not run: %s', 'sitewarden' ), $e->getMessage() )
				)->to_array(),
			];
		}

		return $this->valid_results( $check, $results );
	}

	/**
	 * Keeps the results that belong to the check, as arrays. Checks may come from extensions,
	 * so what they return is not trusted to match the interface.
	 *
	 * @param Check   $check   Check that returned the results.
	 * @param mixed[] $results What it returned.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function valid_results( Check $check, array $results ): array {
		$arrays = [];
		foreach ( $results as $result ) {
			if ( $result instanceof Result && $result->check_id() === $check->id() ) {
				$arrays[] = $result->to_array();
			}
		}
		return $arrays;
	}

	/**
	 * Drops results over MAX_RESULTS_PER_CHECK.
	 *
	 * @param string                           $id      Check id.
	 * @param array<int, array<string, mixed>> $results New results of the check.
	 * @param array<int, array<string, mixed>> $stored  Results stored so far in the run.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function limit_results( string $id, array $results, array $stored ): array {
		$count = 0;
		foreach ( $stored as $result ) {
			if ( ( $result['check_id'] ?? '' ) === $id ) {
				++$count;
			}
		}
		return array_slice( $results, 0, max( 0, self::MAX_RESULTS_PER_CHECK - $count ) );
	}

	/**
	 * Stores the report of a finished run and announces it.
	 *
	 * @param array<string, mixed> $state Finished run.
	 */
	private function complete( array $state ): void {
		$results = [];
		foreach ( $state['results'] as $data ) {
			try {
				$results[] = Result::from_array( $data );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}

		$checks = [];
		$groups = [];
		foreach ( $state['checks'] as $id ) {
			$check = $this->registry->get( $id );
			if ( null !== $check ) {
				$checks[ $id ] = [
					'title' => $check->title(),
					'group' => $check->group(),
				];
				$groups[ $id ] = $check->group();
			}
		}

		$counts  = array_fill_keys( Result::SEVERITIES, 0 );
		$passed  = 0;
		$skipped = 0;
		foreach ( $results as $result ) {
			if ( $result->is_problem() ) {
				++$counts[ $result->severity() ];
			} elseif ( Result::STATUS_PASS === $result->status() ) {
				++$passed;
			} elseif ( Result::STATUS_SKIPPED === $result->status() ) {
				++$skipped;
			}
		}

		$finished_at = $this->now();
		$report      = [
			'id'          => $state['id'],
			'trigger'     => $state['trigger'],
			'started_at'  => $state['started_at'],
			'finished_at' => $finished_at,
			'duration'    => $finished_at - (int) $state['started_at'],
			'score'       => $this->score->calculate( $results, $groups ),
			'counts'      => $counts,
			'passed'      => $passed,
			'skipped'     => $skipped,
			'checks'      => $checks,
			'results'     => array_map(
				static function ( Result $result ): array {
					return $result->to_array();
				},
				$results
			),
		];

		$this->reports->save( $report );
		$this->runs->delete();

		/**
		 * Fires after a scan's report is stored.
		 *
		 * @param array $report The report: score, counts, results and check metadata.
		 */
		$this->hooks->do_action( 'sitewarden_scan_completed', $report );
	}

	/**
	 * The stored run, marked failed first when it stopped making progress.
	 *
	 * @return array<string, mixed>|null
	 */
	private function current_state(): ?array {
		$state = $this->runs->get();
		if ( null === $state || ! isset( $state['id'], $state['status'], $state['checks'] ) || ! is_array( $state['checks'] ) ) {
			return null;
		}

		if ( self::is_active( $state ) && $this->now() - (int) $state['updated_at'] > self::STALL_SECONDS ) {
			$state['status']  = self::STATE_FAILED;
			$state['results'] = [];
			$this->runs->save( $state );
			$this->cron->wp_clear_scheduled_hook( self::TICK_HOOK, [ $state['id'] ] );
		}

		return $state;
	}

	/**
	 * @param array<string, mixed> $state Run state.
	 */
	private static function is_active( array $state ): bool {
		return in_array( $state['status'], [ self::STATE_QUEUED, self::STATE_RUNNING ], true );
	}

	private function schedule_next(): void {
		$state = $this->runs->get();
		if ( null === $state ) {
			return;
		}
		$this->cron->wp_schedule_single_event( time(), self::TICK_HOOK, [ $state['id'] ] );
	}

	private function memory_limit(): int {
		$limit = (int) $this->environment->wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return max( 0, $limit );
	}

	private function now(): int {
		return (int) ( $this->clock )();
	}
}

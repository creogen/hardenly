<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner;

use Merkushin\Wpal\Service\Cron;
use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\CheckRegistry;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Scanner\Scanner;
use Sitewarden\Scanner\Score;
use Sitewarden\Storage\Lock;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Storage\RunStore;
use Sitewarden\Storage\Settings;
use Sitewarden\Tests\OptionsFake;

class ScannerTest extends TestCase {
	use OptionsFake;

	/**
	 * @var float
	 */
	private $now = 1000.0;

	/**
	 * @var Cron&MockObject
	 */
	private $cron;

	/**
	 * @var Hooks&MockObject
	 */
	private $hooks;

	/**
	 * @var Lock&MockObject
	 */
	private $lock;

	/**
	 * Actions fired, as [ hook, args ].
	 *
	 * @var array<int, array{string, mixed[]}>
	 */
	private $actions = [];

	/**
	 * Checks the sitewarden_checks filter returns.
	 *
	 * @var Check[]
	 */
	private $checks = [];

	protected function setUp(): void {
		$this->fake_options();

		$this->cron  = $this->createMock( Cron::class );
		$this->hooks = $this->createMock( Hooks::class );
		$this->hooks->method( 'apply_filters' )->willReturnCallback(
			function () {
				return $this->checks;
			}
		);
		$this->hooks->method( 'do_action' )->willReturnCallback(
			function ( string $hook, ...$args ): void {
				$this->actions[] = [ $hook, $args ];
			}
		);
		$environment = $this->createMock( Environment::class );
		$environment->method( 'wp_convert_hr_to_bytes' )->willReturn( -1 );

		ServiceFactory::set_custom_cron( $this->cron );
		ServiceFactory::set_custom_hooks( $this->hooks );
		ServiceFactory::set_custom_environment( $environment );

		$this->lock = $this->createMock( Lock::class );
		$this->lock->method( 'acquire' )->willReturn( true );
	}

	private function scanner(): Scanner {
		return new Scanner(
			new CheckRegistry( [] ),
			new RunStore(),
			new ReportStore(),
			new Settings(),
			$this->lock,
			new Score(),
			function (): float {
				return $this->now;
			}
		);
	}

	/**
	 * A check that takes the given seconds of the fake clock on each call.
	 *
	 * @param string   $id      Check id.
	 * @param float    $seconds Seconds per call.
	 * @param Result[] $results Results it returns.
	 */
	private function slow_check( string $id, float $seconds, array $results = [] ): FakeCheck {
		return new FakeCheck(
			$id,
			Group::CONFIG,
			function () use ( $seconds, $results ): array {
				$this->now += $seconds;
				return $results;
			}
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function report(): ?array {
		return ( new ReportStore() )->latest();
	}

	public function testStart_NoRun_QueuesRunAndSchedulesTick(): void {
		$this->checks = [ new FakeCheck( 'a' ) ];
		$this->cron->expects( self::once() )
			->method( 'wp_schedule_single_event' )
			->with( self::anything(), Scanner::TICK_HOOK, self::callback( 'is_array' ) );

		self::assertTrue( $this->scanner()->start() );

		$status = $this->scanner()->status();
		self::assertSame( Scanner::STATE_QUEUED, $status['state'] );
		self::assertSame( 1, $status['progress']['total'] );
	}

	public function testStart_RunActive_ReturnsFalse(): void {
		$scanner = $this->scanner();
		$scanner->start();

		self::assertFalse( $scanner->start() );
	}

	public function testStart_DisabledGroup_LeavesItsChecksOut(): void {
		$this->checks                             = [ new FakeCheck( 'a', Group::CONFIG ), new FakeCheck( 'b', Group::FILES ) ];
		$this->stored_options[ Settings::OPTION ] = [ 'disabled_groups' => [ Group::FILES ] ];

		$this->scanner()->start();

		self::assertSame( 1, $this->scanner()->status()['progress']['total'] );
	}

	public function testTick_AllChecksFit_StoresReportAndClearsRun(): void {
		$this->checks = [
			new FakeCheck(
				'a',
				Group::CONFIG,
				static function (): array {
					return [ Result::fail( 'a', Result::SEVERITY_HIGH, 'Problem' ) ];
				}
			),
			new FakeCheck(
				'b',
				Group::FILES,
				static function (): array {
					return [ Result::pass( 'b', 'Fine' ) ];
				}
			),
		];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick();

		$report = $this->report();
		self::assertNotNull( $report );
		self::assertSame( 85, $report['score'] );
		self::assertSame( 1, $report['counts']['high'] );
		self::assertSame( 1, $report['passed'] );
		self::assertCount( 2, $report['results'] );
		self::assertSame(
			[
				'title' => 'Check b',
				'group' => Group::FILES,
			],
			$report['checks']['b']
		);
		self::assertSame( Scanner::STATE_IDLE, $scanner->status()['state'] );
		self::assertCount( 1, ( new ReportStore() )->history() );
	}

	public function testTick_Completed_FiresScanCompletedWithReport(): void {
		$scanner = $this->scanner();
		$scanner->start();

		$scanner->tick();

		self::assertSame( 'sitewarden_scan_completed', $this->actions[0][0] );
		self::assertSame( $this->report(), $this->actions[0][1][0] );
	}

	public function testTick_BudgetSpent_StopsAndSchedulesNextBatch(): void {
		$this->checks = [ $this->slow_check( 'a', 6 ), $this->slow_check( 'b', 6 ), $this->slow_check( 'c', 6 ) ];
		$scanner      = $this->scanner();
		$scanner->start();
		$this->cron->expects( self::once() )->method( 'wp_schedule_single_event' );

		$scanner->tick( 10.0 );

		$status = $scanner->status();
		self::assertSame( Scanner::STATE_RUNNING, $status['state'] );
		self::assertSame( 2, $status['progress']['done'] );
		self::assertSame( 'c', $status['progress']['current']['id'] ?? null );
		self::assertNull( $this->report() );
	}

	public function testTick_SeveralBatches_RunsEachCheckOnce(): void {
		$this->checks = [ $this->slow_check( 'a', 6 ), $this->slow_check( 'b', 6 ), $this->slow_check( 'c', 6 ) ];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick( 5.0 );
		$scanner->tick( 5.0 );
		$scanner->tick( 5.0 );

		self::assertNotNull( $this->report() );
		foreach ( $this->checks as $check ) {
			self::assertInstanceOf( FakeCheck::class, $check );
			self::assertSame( 1, $check->calls );
		}
	}

	public function testTick_CheckPauses_ResumesWithCursorAndKeepsAllResults(): void {
		$cursors      = [];
		$this->checks = [
			new FakeCheck(
				'walk',
				Group::FILES,
				function ( Context $context ) use ( &$cursors ): array {
					$cursor     = $context->cursor();
					$cursors[]  = $cursor;
					$this->now += 6;
					$offset     = $cursor['offset'] ?? 0;
					if ( $offset < 2 ) {
						$context->pause( [ 'offset' => $offset + 1 ] );
					}
					return [ Result::fail( 'walk', Result::SEVERITY_LOW, "Part $offset" ) ];
				}
			),
		];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick( 5.0 );
		$scanner->tick( 5.0 );
		$scanner->tick( 5.0 );

		self::assertSame( [ null, [ 'offset' => 1 ], [ 'offset' => 2 ] ], $cursors );
		$report = $this->report();
		self::assertNotNull( $report );
		self::assertSame( [ 'Part 0', 'Part 1', 'Part 2' ], array_column( $report['results'], 'message' ) );
	}

	public function testTick_CheckThrows_ReportsItSkippedAndGoesOn(): void {
		$this->checks = [
			new FakeCheck(
				'broken',
				Group::CONFIG,
				static function ( Context $context ): array {
					$context->pause( [ 'offset' => 1 ] );
					throw new RuntimeException( 'Boom' );
				}
			),
			new FakeCheck(
				'b',
				Group::CONFIG,
				static function (): array {
					return [ Result::pass( 'b', 'Fine' ) ];
				}
			),
		];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick();

		$report = $this->report();
		self::assertNotNull( $report );
		self::assertSame( Result::STATUS_SKIPPED, $report['results'][0]['status'] );
		self::assertStringContainsString( 'Boom', $report['results'][0]['message'] );
		self::assertSame( 1, $report['skipped'] );
		self::assertSame( 1, $report['passed'] );
	}

	public function testTick_ResultOfAnotherCheck_IsDropped(): void {
		$this->checks = [
			new FakeCheck(
				'a',
				Group::CONFIG,
				static function (): array {
					return [ Result::fail( 'other', Result::SEVERITY_CRITICAL, 'Spoofed' ), 'not a result' ];
				}
			),
		];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick();

		$report = $this->report();
		self::assertNotNull( $report );
		self::assertSame( [], $report['results'] );
		self::assertSame( 100, $report['score'] );
	}

	public function testTick_TooManyResults_KeepsTheLimitPerCheck(): void {
		$this->checks = [
			new FakeCheck(
				'a',
				Group::CONFIG,
				static function (): array {
					return array_fill( 0, Scanner::MAX_RESULTS_PER_CHECK + 5, Result::info( 'a', 'Note' ) );
				}
			),
		];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->tick();

		$report = $this->report();
		self::assertNotNull( $report );
		self::assertCount( Scanner::MAX_RESULTS_PER_CHECK, $report['results'] );
	}

	public function testTick_LockHeld_DoesNothing(): void {
		$check        = new FakeCheck( 'a' );
		$this->checks = [ $check ];
		$lock         = $this->createMock( Lock::class );
		$lock->method( 'acquire' )->willReturn( false );
		$this->lock = $lock;
		$scanner    = $this->scanner();
		$scanner->start();

		$scanner->tick();

		self::assertSame( 0, $check->calls );
		self::assertSame( Scanner::STATE_QUEUED, $scanner->status()['state'] );
	}

	public function testTick_Always_ReleasesTheLock(): void {
		$scanner = $this->scanner();
		$scanner->start();
		$this->lock->expects( self::once() )->method( 'release' );

		$scanner->tick();
	}

	public function testTick_NoRun_DoesNotTakeTheLock(): void {
		$this->lock->expects( self::never() )->method( 'acquire' );

		$this->scanner()->tick();
	}

	public function testHandleTickEvent_OtherRun_DoesNothing(): void {
		$check        = new FakeCheck( 'a' );
		$this->checks = [ $check ];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->handle_tick_event( 'another-run' );

		self::assertSame( 0, $check->calls );
	}

	public function testHandleTickEvent_CurrentRun_RunsABatch(): void {
		$check        = new FakeCheck( 'a' );
		$this->checks = [ $check ];
		$scanner      = $this->scanner();
		$scanner->start();

		$scanner->handle_tick_event( $scanner->status()['run_id'] );

		self::assertSame( 1, $check->calls );
	}

	public function testStop_RunActive_MarksItStoppedAndClearsTheEvent(): void {
		$scanner = $this->scanner();
		$scanner->start();
		$this->cron->expects( self::once() )
			->method( 'wp_clear_scheduled_hook' )
			->with( Scanner::TICK_HOOK, [ $scanner->status()['run_id'] ] );

		$scanner->stop();

		self::assertSame( Scanner::STATE_STOPPED, $scanner->status()['state'] );
		self::assertTrue( $scanner->start(), 'A new scan can start.' );
	}

	public function testTick_StoppedDuringBatch_DoesNotOverwriteTheStop(): void {
		$scanner      = $this->scanner();
		$this->checks = [
			new FakeCheck(
				'a',
				Group::CONFIG,
				static function () use ( $scanner ): array {
					$scanner->stop();
					return [];
				}
			),
			new FakeCheck( 'b' ),
		];
		$scanner->start();

		$scanner->tick();

		self::assertSame( Scanner::STATE_STOPPED, $scanner->status()['state'] );
		self::assertNull( $this->report() );
	}

	public function testStatus_NoProgressForLong_MarksRunFailed(): void {
		$scanner = $this->scanner();
		$scanner->start();

		$this->now += Scanner::STALL_SECONDS + 1;

		self::assertSame( Scanner::STATE_FAILED, $scanner->status()['state'] );
		self::assertTrue( $scanner->start(), 'A new scan can start.' );
	}
}

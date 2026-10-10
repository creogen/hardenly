<?php declare( strict_types=1 );

namespace Sitewarden\Storage;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The latest full report and short summaries of the last scans.
 */
class ReportStore {
	public const LATEST_OPTION  = 'sitewarden_latest_report';
	public const HISTORY_OPTION = 'sitewarden_report_history';
	public const HISTORY_SIZE   = 10;

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function latest(): ?array {
		$report = $this->options->get_option( self::LATEST_OPTION, null );
		return is_array( $report ) ? $report : null;
	}

	/**
	 * Summaries of the last scans, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function history(): array {
		$history = $this->options->get_option( self::HISTORY_OPTION, [] );
		return is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : [];
	}

	/**
	 * Stores a finished report as the latest and adds its summary to the history.
	 *
	 * @param array<string, mixed> $report Report built by the scanner.
	 */
	public function save( array $report ): void {
		$this->options->update_option( self::LATEST_OPTION, $report, false );

		$summary = [
			'id'          => $report['id'] ?? '',
			'finished_at' => $report['finished_at'] ?? 0,
			'score'       => $report['score'] ?? 0,
			'counts'      => $report['counts'] ?? [],
		];
		$history = array_slice( array_merge( [ $summary ], $this->history() ), 0, self::HISTORY_SIZE );
		$this->options->update_option( self::HISTORY_OPTION, $history, false );
	}
}

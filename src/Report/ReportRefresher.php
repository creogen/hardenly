<?php declare( strict_types=1 );

namespace Hardenly\Report;

use Hardenly\Storage\ReportStore;
use Hardenly\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the latest report's score and counts in line with the ignored findings.
 *
 * Runs whenever the settings change, whether from the dashboard or the Settings screen.
 */
class ReportRefresher {
	/**
	 * @var ReportStore
	 */
	private $reports;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Summary
	 */
	private $summary;

	public function __construct( ReportStore $reports, Settings $settings, Summary $summary ) {
		$this->reports  = $reports;
		$this->settings = $settings;
		$this->summary  = $summary;
	}

	public function refresh(): void {
		$report = $this->reports->latest();
		if ( null !== $report ) {
			$this->reports->replace_latest( $this->summary->apply( $report, $this->settings->ignored_ids() ) );
		}
	}
}

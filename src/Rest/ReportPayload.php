<?php declare( strict_types=1 );

namespace Sitewarden\Rest;

use Sitewarden\Scanner\Group;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * What the dashboard gets about the latest report.
 */
class ReportPayload {
	/**
	 * @var ReportStore
	 */
	private $reports;

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( ReportStore $reports, Settings $settings ) {
		$this->reports  = $reports;
		$this->settings = $settings;
	}

	/**
	 * @return array{report: array<string, mixed>|null, history: array<int, array<string, mixed>>, groups: array<string, string>, skip_files: string[], vulnerability_lookup: bool}
	 */
	public function build(): array {
		return [
			'report'               => $this->reports->latest(),
			'history'              => $this->reports->history(),
			'groups'               => Group::labels(),
			'skip_files'           => (array) $this->settings->get()['skip_files'],
			'vulnerability_lookup' => $this->settings->vulnerability_lookup_ready(),
		];
	}
}

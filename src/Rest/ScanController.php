<?php declare( strict_types=1 );

namespace Hardenly\Rest;

use Hardenly\Admin\Access;
use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Scanner;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes for the dashboard: start, follow and stop a scan, and read the report.
 *
 * All routes need the Access capability (`manage_options`, or super admin on multisite). The dashboard sends the REST nonce through api-fetch.
 */
class ScanController {
	public const NAMESPACE = 'hardenly/v1';

	/**
	 * Seconds of work per tick requested by the dashboard: short, so progress updates often.
	 */
	public const TICK_BUDGET = 5.0;

	/**
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * @var ReportPayload
	 */
	private $payload;

	/**
	 * @var RestApi
	 */
	private $rest;

	/**
	 * @var Access
	 */
	private $access;

	public function __construct( Scanner $scanner, ReportPayload $payload ) {
		$this->scanner = $scanner;
		$this->payload = $payload;
		$this->rest    = ServiceFactory::create_rest_api();
		$this->access  = new Access();
	}

	public function register_routes(): void {
		$permission = [ $this, 'can_manage' ];

		$this->rest->register_rest_route(
			self::NAMESPACE,
			'/scan',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_status' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'start' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'stop' ],
					'permission_callback' => $permission,
				],
			]
		);

		$this->rest->register_rest_route(
			self::NAMESPACE,
			'/scan/tick',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'tick' ],
				'permission_callback' => $permission,
			]
		);

		$this->rest->register_rest_route(
			self::NAMESPACE,
			'/report',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_report' ],
				'permission_callback' => $permission,
			]
		);
	}

	public function can_manage(): bool {
		return $this->access->can_manage();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		return $this->scanner->status();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function start() {
		if ( ! $this->scanner->start( 'manual' ) ) {
			return new WP_Error(
				'hardenly_scan_running',
				__( 'A scan is already running.', 'hardenly' ),
				[ 'status' => 409 ]
			);
		}
		return $this->scanner->status();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function stop(): array {
		$this->scanner->stop();
		return $this->scanner->status();
	}

	/**
	 * Runs a short batch while the dashboard is open, so a scan progresses even when WP-Cron
	 * is slow or disabled.
	 *
	 * @return array<string, mixed>
	 */
	public function tick(): array {
		$this->scanner->tick( self::TICK_BUDGET );
		return $this->scanner->status();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		return $this->payload->build();
	}
}

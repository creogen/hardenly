<?php declare( strict_types=1 );

namespace Hardenly\Rest;

use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Report\ReportRefresher;
use Hardenly\Report\Summary;
use Hardenly\Storage\ReportStore;
use Hardenly\Storage\Settings;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes for the dashboard's "Ignore" and "Show again" buttons and "Files to skip".
 *
 * Ignoring a finding hides it from the score, counts and email; it changes nothing on the site.
 */
class FindingsController {
	/**
	 * @var ScanController
	 */
	private $scans;

	/**
	 * @var ReportStore
	 */
	private $reports;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var ReportRefresher
	 */
	private $refresher;

	/**
	 * @var ReportPayload
	 */
	private $payload;

	/**
	 * @var RestApi
	 */
	private $rest;

	/**
	 * @var Users
	 */
	private $users;

	/**
	 * @param ScanController $scans    Provides the permission check.
	 * @param ReportStore    $reports  Latest report.
	 * @param Settings       $settings Ignored findings and files to skip.
	 * @param ReportRefresher $refresher Recomputes score and counts.
	 */
	public function __construct( ScanController $scans, ReportStore $reports, Settings $settings, ReportRefresher $refresher ) {
		$this->scans     = $scans;
		$this->reports   = $reports;
		$this->settings  = $settings;
		$this->refresher = $refresher;
		$this->payload   = new ReportPayload( $reports, $settings );
		$this->rest      = ServiceFactory::create_rest_api();
		$this->users     = ServiceFactory::create_users();
	}

	public function register_routes(): void {
		$id = [
			'id' => [
				'type'     => 'string',
				'required' => true,
			],
		];

		$this->rest->register_rest_route(
			ScanController::NAMESPACE,
			'/findings/ignore',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ignore' ],
				'permission_callback' => [ $this->scans, 'can_manage' ],
				'args'                => $id,
			]
		);
		$this->rest->register_rest_route(
			ScanController::NAMESPACE,
			'/findings/restore',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'restore' ],
				'permission_callback' => [ $this->scans, 'can_manage' ],
				'args'                => $id,
			]
		);
		$this->rest->register_rest_route(
			ScanController::NAMESPACE,
			'/files/skip',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'skip_file' ],
				'permission_callback' => [ $this->scans, 'can_manage' ],
				'args'                => [
					'path' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function ignore( WP_REST_Request $request ) {
		$id     = Settings::clean_id( (string) $request->get_param( 'id' ) );
		$report = $this->reports->latest();
		$found  = null;
		foreach ( null === $report ? [] : (array) ( $report['results'] ?? [] ) as $result ) {
			if ( is_array( $result ) && Summary::finding_id( $result ) === $id ) {
				$found = $result;
				break;
			}
		}
		if ( '' === $id || null === $found ) {
			return self::not_found();
		}

		$user = $this->users->wp_get_current_user();
		$this->settings->ignore( $id, (string) $found['message'], isset( $user->user_login ) ? (string) $user->user_login : '' );
		return $this->refreshed();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function restore( WP_REST_Request $request ) {
		$id = Settings::clean_id( (string) $request->get_param( 'id' ) );
		if ( '' === $id ) {
			return self::not_found();
		}
		$this->settings->restore( $id );
		return $this->refreshed();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function skip_file( WP_REST_Request $request ) {
		$path = Settings::clean_path( (string) $request->get_param( 'path' ) );
		if ( '' === $path ) {
			return new WP_Error( 'hardenly_invalid_path', __( 'This is not a path inside the WordPress folder.', 'hardenly' ), [ 'status' => 400 ] );
		}
		$this->settings->skip_file( $path );
		return $this->payload->build();
	}

	/**
	 * The report data after a change to the ignored findings.
	 *
	 * Saving the settings already refreshed the report through the `update_option_` hook; this
	 * refreshes it again so the answer is right even where that hook is not registered.
	 *
	 * @return array<string, mixed>
	 */
	private function refreshed(): array {
		$this->refresher->refresh();
		return $this->payload->build();
	}

	private static function not_found(): WP_Error {
		return new WP_Error( 'hardenly_finding_not_found', __( 'This finding is not in the latest report.', 'hardenly' ), [ 'status' => 404 ] );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Rest;

use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Hardenly\Report\ReportRefresher;
use Hardenly\Report\Summary;
use Hardenly\Rest\FindingsController;
use Hardenly\Rest\ScanController;
use Hardenly\Scanner\Result;
use Hardenly\Scanner\Score;
use Hardenly\Storage\ReportStore;
use Hardenly\Storage\Settings;
use Hardenly\Tests\OptionsFake;
use WP_Error;
use WP_REST_Request;

class FindingsControllerTest extends TestCase {
	use OptionsFake;

	/**
	 * @var array<int, array{string, string, array<string, mixed>}>
	 */
	private $routes = [];

	protected function setUp(): void {
		$this->fake_options();
		$rest = $this->createMock( RestApi::class );
		$rest->method( 'register_rest_route' )->willReturnCallback(
			function ( string $route_namespace, string $route, array $args ): bool {
				$this->routes[] = [ $route_namespace, $route, $args ];
				return true;
			}
		);
		$users = $this->createMock( Users::class );
		$users->method( 'wp_get_current_user' )->willReturn( (object) [ 'user_login' => 'admin' ] );
		$sanitization = $this->createMock( Sanitization::class );
		foreach ( [ 'sanitize_key', 'sanitize_text_field', 'sanitize_user', 'sanitize_email' ] as $method ) {
			$sanitization->method( $method )->willReturnArgument( 0 );
		}
		ServiceFactory::set_custom_rest_api( $rest );
		ServiceFactory::set_custom_users( $users );
		ServiceFactory::set_custom_sanitization( $sanitization );

		( new ReportStore() )->save(
			( new Summary( new Score() ) )->apply(
				[
					'id'      => 'r1',
					'checks'  => [ 'config.salts' => [ 'group' => 'config' ] ],
					'results' => [ Result::fail( 'config.salts', Result::SEVERITY_HIGH, 'Security keys are weak' )->to_array() ],
				],
				[]
			)
		);
	}

	private static function request( string $key, string $value ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( $key, $value );
		return $request;
	}

	private function controller(): FindingsController {
		return new FindingsController( $this->createMock( ScanController::class ), new ReportStore(), new Settings(), new ReportRefresher( new ReportStore(), new Settings(), new Summary( new Score() ) ) );
	}

	public function testRegisterRoutes_Always_ProtectsEveryRoute(): void {
		$scans = $this->createMock( ScanController::class );

		( new FindingsController( $scans, new ReportStore(), new Settings(), new ReportRefresher( new ReportStore(), new Settings(), new Summary( new Score() ) ) ) )->register_routes();

		self::assertSame( [ '/findings/ignore', '/findings/restore', '/files/skip' ], array_column( $this->routes, 1 ) );
		foreach ( $this->routes as $route ) {
			self::assertSame( [ $scans, 'can_manage' ], $route[2]['permission_callback'] );
		}
	}

	public function testIgnore_KnownFinding_HidesItAndRecomputesTheScore(): void {
		$response = $this->controller()->ignore( self::request( 'id', 'config.salts' ) );

		self::assertIsArray( $response );
		self::assertSame( 100, $response['report']['score'] );
		self::assertTrue( $response['report']['results'][0]['ignored'] );
		self::assertSame( 100, $response['history'][0]['score'] );
		$ignored = ( new Settings() )->get()['ignored'];
		self::assertSame( 'Security keys are weak', $ignored['config.salts']['message'], 'The message comes from the report, not the request.' );
		self::assertSame( 'admin', $ignored['config.salts']['by'] );
	}

	public function testIgnore_UnknownFinding_IsNotFound(): void {
		$response = $this->controller()->ignore( self::request( 'id', 'config.other' ) );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( [ 'status' => 404 ], $response->get_error_data() );
		self::assertSame( [], ( new Settings() )->ignored_ids() );
	}

	public function testRestore_IgnoredFinding_CountsItAgain(): void {
		$controller = $this->controller();
		$controller->ignore( self::request( 'id', 'config.salts' ) );

		$response = $controller->restore( self::request( 'id', 'config.salts' ) );

		self::assertIsArray( $response );
		self::assertSame( 85, $response['report']['score'] );
		self::assertSame( [], ( new Settings() )->ignored_ids() );
	}

	public function testSkipFile_ValidPath_AddsItToFilesToSkip(): void {
		$response = $this->controller()->skip_file( self::request( 'path', 'wp-content/uploads/x.php' ) );

		self::assertIsArray( $response );
		self::assertSame( [ 'wp-content/uploads/x.php' ], $response['skip_files'] );
	}

	public function testSkipFile_PathOutsideWordPress_IsRejected(): void {
		$response = $this->controller()->skip_file( self::request( 'path', '../wp-config.php' ) );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( [], ( new Settings() )->get()['skip_files'] );
	}
}

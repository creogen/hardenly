<?php declare( strict_types=1 );

namespace Hardenly\Tests\Rest;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Hardenly\Tests\SitesFake;
use Hardenly\Rest\ScanController;
use Hardenly\Scanner\Scanner;
use Hardenly\Rest\ReportPayload;
use Hardenly\Storage\ReportStore;
use Hardenly\Storage\Settings;
use WP_Error;

class ScanControllerTest extends TestCase {
	use SitesFake;

	/**
	 * @var RestApi&MockObject
	 */
	private $rest;

	/**
	 * @var Capabilities&MockObject
	 */
	private $capabilities;

	/**
	 * @var Scanner&MockObject
	 */
	private $scanner;

	/**
	 * @var ReportStore&MockObject
	 */
	private $reports;

	protected function setUp(): void {
		$this->fake_sites();
		$this->rest         = $this->createMock( RestApi::class );
		$this->capabilities = $this->createMock( Capabilities::class );
		$this->scanner      = $this->createMock( Scanner::class );
		$this->reports      = $this->createMock( ReportStore::class );
		ServiceFactory::set_custom_rest_api( $this->rest );
		ServiceFactory::set_custom_capabilities( $this->capabilities );
	}

	private function controller(): ScanController {
		$settings = $this->createMock( Settings::class );
		$settings->method( 'get' )->willReturn( [ 'skip_files' => [ 'a.php' ] ] );
		return new ScanController( $this->scanner, new ReportPayload( $this->reports, $settings ) );
	}

	public function testRegisterRoutes_Always_ProtectsEveryEndpoint(): void {
		$controller = $this->controller();
		$endpoints  = [];
		$this->rest->method( 'register_rest_route' )->willReturnCallback(
			function ( string $route_namespace, string $route, array $args ) use ( &$endpoints ): bool {
				self::assertSame( ScanController::NAMESPACE, $route_namespace );
				foreach ( isset( $args['methods'] ) ? [ $args ] : $args as $endpoint ) {
					$endpoints[ $endpoint['methods'] . ' ' . $route ] = $endpoint['permission_callback'];
				}
				return true;
			}
		);

		$controller->register_routes();

		self::assertSame( [ 'GET /scan', 'POST /scan', 'DELETE /scan', 'POST /scan/tick', 'GET /report' ], array_keys( $endpoints ) );
		foreach ( $endpoints as $permission ) {
			self::assertSame( [ $controller, 'can_manage' ], $permission );
		}
	}

	public function testCanManage_Administrator_IsTrue(): void {
		$this->capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( true );

		self::assertTrue( $this->controller()->can_manage() );
	}

	public function testCanManage_OtherUser_IsFalse(): void {
		$this->capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( false );

		self::assertFalse( $this->controller()->can_manage() );
	}

	public function testStart_ScanRunning_ReturnsConflict(): void {
		$this->scanner->method( 'start' )->willReturn( false );

		$response = $this->controller()->start();

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( [ 'status' => 409 ], $response->get_error_data() );
	}

	public function testStart_NoScan_StartsAndReturnsStatus(): void {
		$this->scanner->expects( self::once() )->method( 'start' )->with( 'manual' )->willReturn( true );
		$this->scanner->method( 'status' )->willReturn( [ 'state' => 'queued' ] );

		self::assertSame( [ 'state' => 'queued' ], $this->controller()->start() );
	}

	public function testTick_Always_RunsAShortBatch(): void {
		$this->scanner->expects( self::once() )->method( 'tick' )->with( ScanController::TICK_BUDGET );
		$this->scanner->method( 'status' )->willReturn( [ 'state' => 'running' ] );

		self::assertSame( [ 'state' => 'running' ], $this->controller()->tick() );
	}

	public function testStop_Always_StopsTheScan(): void {
		$this->scanner->expects( self::once() )->method( 'stop' );

		$this->controller()->stop();
	}

	public function testGetReport_Always_ReturnsReportHistoryAndGroups(): void {
		$this->reports->method( 'latest' )->willReturn( [ 'score' => 90 ] );
		$this->reports->method( 'history' )->willReturn( [ [ 'score' => 90 ] ] );

		$response = $this->controller()->get_report();

		self::assertSame( [ 'score' => 90 ], $response['report'] );
		self::assertSame( [ [ 'score' => 90 ] ], $response['history'] );
		self::assertArrayHasKey( 'files', $response['groups'] );
		self::assertSame( [ 'a.php' ], $response['skip_files'] );
	}
}

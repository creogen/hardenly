<?php declare( strict_types=1 );

namespace Hardenly\Tests\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Hardenly\Admin\DashboardPage;

class DashboardPageTest extends TestCase {
	private function render( bool $can_manage ): string {
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( $can_manage );
		ServiceFactory::set_custom_capabilities( $capabilities );

		ob_start();
		( new DashboardPage() )->render();
		return (string) ob_get_clean();
	}

	public function testRender_Administrator_OutputsTheAppRoot(): void {
		self::assertStringContainsString( 'id="hardenly-dashboard"', $this->render( true ) );
	}

	public function testRender_OtherUser_OutputsNothing(): void {
		self::assertSame( '', $this->render( false ) );
	}
}

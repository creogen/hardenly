<?php declare( strict_types=1 );

namespace Hardenly\Tests\Admin;

use Hardenly\Admin\Access;
use Hardenly\Tests\SitesFake;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;

class AccessTest extends TestCase {
	use SitesFake;

	public function testCapability_SingleSite_IsManageOptions(): void {
		$this->fake_sites( false );

		self::assertSame( 'manage_options', ( new Access() )->capability() );
	}

	public function testCapability_Multisite_IsForSuperAdmins(): void {
		$this->fake_sites( true );

		self::assertSame( 'manage_network_options', ( new Access() )->capability() );
	}

	public function testCanManage_Multisite_ChecksTheNetworkCapability(): void {
		$this->fake_sites( true );
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->expects( self::once() )->method( 'current_user_can' )->with( 'manage_network_options' )->willReturn( false );
		ServiceFactory::set_custom_capabilities( $capabilities );

		self::assertFalse( ( new Access() )->can_manage(), 'A site administrator in a network has manage_options but not this.' );
	}
}

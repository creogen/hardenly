<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Admin;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Admin\DashboardPage;
use Sitewarden\Admin\Menu;
use Sitewarden\Admin\SettingsPage;

class MenuTest extends TestCase {
	/**
	 * @var Plugins&MockObject
	 */
	private $plugins;

	protected function setUp(): void {
		$this->plugins = $this->createMock( Plugins::class );
		ServiceFactory::set_custom_plugins( $this->plugins );
	}

	public function testRegister_Always_AddsTopLevelMenuForAdministrators(): void {
		$dashboard = new DashboardPage();
		$this->plugins->expects( self::once() )
			->method( 'add_menu_page' )
			->with( 'Sitewarden', 'Sitewarden', 'manage_options', 'sitewarden', [ $dashboard, 'render' ], 'dashicons-shield' );
		$submenus = [];
		$this->plugins->method( 'add_submenu_page' )->willReturnCallback(
			function ( string $parent_slug, string $page_title, string $menu_title, string $capability, string $slug ) use ( &$submenus ): string {
				$submenus[] = [ $parent_slug, $menu_title, $capability, $slug ];
				return 'hook';
			}
		);

		( new Menu( $dashboard, $this->createMock( SettingsPage::class ) ) )->register();

		self::assertSame(
			[
				[ 'sitewarden', 'Dashboard', 'manage_options', 'sitewarden' ],
				[ 'sitewarden', 'Settings', 'manage_options', 'sitewarden-settings' ],
			],
			$submenus
		);
	}

	public function testIsPluginScreen_AfterRegister_KnowsItsScreens(): void {
		$this->plugins->method( 'add_menu_page' )->willReturn( 'toplevel_page_sitewarden' );
		$this->plugins->method( 'add_submenu_page' )->willReturn( false );
		$menu = new Menu( new DashboardPage(), $this->createMock( SettingsPage::class ) );

		$menu->register();

		self::assertTrue( $menu->is_dashboard_screen( 'toplevel_page_sitewarden' ) );
		self::assertFalse( $menu->is_dashboard_screen( 'index.php' ) );
	}
}

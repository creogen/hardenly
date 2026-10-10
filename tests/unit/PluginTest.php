<?php declare( strict_types=1 );

namespace Sitewarden\Tests;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Admin\Menu;
use Sitewarden\Plugin;
use Sitewarden\Rest\ScanController;
use Sitewarden\Scanner\Scanner;

/**
 * Each WordPress function the plugin calls goes through a WPAL service; the tests swap the
 * services for mocks with ServiceFactory::set_custom_*() and check the calls.
 */
class PluginTest extends TestCase {
	use OptionsFake;

	private const PLUGIN_URL = 'https://example.com/wp-content/plugins/sitewarden/';

	/**
	 * A plugin directory with a build of assets: admin has a stylesheet and uses wp-i18n.
	 */
	private const PLUGIN_FILE = __DIR__ . '/../fixtures/plugin/sitewarden.php';

	private const SCREEN = 'toplevel_page_sitewarden';

	/**
	 * @var Hooks&MockObject
	 */
	private $hooks;

	/**
	 * @var Assets&MockObject
	 */
	private $assets;

	/**
	 * Callbacks added with add_action(), by hook.
	 *
	 * @var array<string, callable>
	 */
	private $actions = [];

	protected function setUp(): void {
		$this->fake_options();
		$this->hooks  = $this->createMock( Hooks::class );
		$this->assets = $this->createMock( Assets::class );
		$plugins      = $this->createMock( Plugins::class );
		$plugins->method( 'plugin_dir_url' )->willReturn( self::PLUGIN_URL );
		$plugins->method( 'add_menu_page' )->willReturn( self::SCREEN );

		$this->hooks->method( 'add_action' )->willReturnCallback(
			function ( string $hook, callable $callback ): bool {
				$this->actions[ $hook ] = $callback;
				return true;
			}
		);
		$this->hooks->method( 'apply_filters' )->willReturnArgument( 1 );

		ServiceFactory::set_custom_hooks( $this->hooks );
		ServiceFactory::set_custom_assets( $this->assets );
		ServiceFactory::set_custom_plugins( $plugins );
	}

	/**
	 * A plugin set up as on a request to wp-admin, after the menu is registered.
	 *
	 * @param string $file Main plugin file.
	 */
	private function plugin_in_admin( string $file = self::PLUGIN_FILE ): Plugin {
		$plugin = new Plugin( $file );
		$plugin->init();
		( $this->actions['admin_menu'] )();
		return $plugin;
	}

	public function testInit_Always_HooksTheParts(): void {
		$plugin = new Plugin( self::PLUGIN_FILE );

		$plugin->init();

		self::assertSame( [ 'admin_menu', 'admin_enqueue_scripts', 'rest_api_init', Scanner::TICK_HOOK, 'wp_login' ], array_keys( $this->actions ) );
		self::assertEquals( [ $plugin, 'enqueue_admin_assets' ], $this->actions['admin_enqueue_scripts'] );
		self::assertIsArray( $this->actions['admin_menu'] );
		self::assertInstanceOf( Menu::class, $this->actions['admin_menu'][0] );
		self::assertIsArray( $this->actions['rest_api_init'] );
		self::assertInstanceOf( ScanController::class, $this->actions['rest_api_init'][0] );
		self::assertIsArray( $this->actions[ Scanner::TICK_HOOK ] );
		self::assertInstanceOf( Scanner::class, $this->actions[ Scanner::TICK_HOOK ][0] );
	}

	public function testEnqueueAdminAssets_OtherScreen_EnqueuesNothing(): void {
		$this->assets->expects( self::never() )->method( 'wp_enqueue_script' );
		$this->assets->expects( self::never() )->method( 'wp_enqueue_style' );

		$this->plugin_in_admin()->enqueue_admin_assets( 'index.php' );
	}

	public function testEnqueueAdminAssets_BeforeInit_EnqueuesNothing(): void {
		$this->assets->expects( self::never() )->method( 'wp_enqueue_script' );

		( new Plugin( self::PLUGIN_FILE ) )->enqueue_admin_assets( self::SCREEN );
	}

	public function testEnqueueAdminAssets_WithStylesheet_EnqueuesStyleWithBuildVersion(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_enqueue_style' )
			->with( 'sitewarden-admin', self::PLUGIN_URL . 'assets/build/admin.css', [], 'admin-hash' );

		$this->plugin_in_admin()->enqueue_admin_assets( self::SCREEN );
	}

	public function testEnqueueAdminAssets_WithStylesheet_UsesRtlStylesheetForRtlLanguages(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_style_add_data' )
			->with( 'sitewarden-admin', 'rtl', 'replace' );

		$this->plugin_in_admin()->enqueue_admin_assets( self::SCREEN );
	}

	public function testEnqueueAdminAssets_Always_EnqueuesScriptWithBuildDependencies(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_enqueue_script' )
			->with(
				'sitewarden-admin',
				self::PLUGIN_URL . 'assets/build/admin.js',
				[ 'wp-i18n' ],
				'admin-hash',
				[ 'in_footer' => true ]
			);

		$this->plugin_in_admin()->enqueue_admin_assets( self::SCREEN );
	}

	public function testEnqueueAdminAssets_ScriptUsesI18n_LoadsScriptTranslations(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_set_script_translations' )
			->with( 'sitewarden-admin', 'sitewarden' );

		$this->plugin_in_admin()->enqueue_admin_assets( self::SCREEN );
	}

	public function testEnqueueAdminAssets_NotBuilt_EnqueuesNothing(): void {
		$this->assets->expects( self::never() )->method( 'wp_enqueue_style' );
		$this->assets->expects( self::never() )->method( 'wp_enqueue_script' );

		$this->plugin_in_admin( __DIR__ . '/sitewarden.php' )->enqueue_admin_assets( self::SCREEN );
	}
}

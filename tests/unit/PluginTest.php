<?php declare( strict_types=1 );

namespace Sitewarden\Tests;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Admin\Menu;
use Sitewarden\Admin\SettingsPage;
use Sitewarden\Cron\Schedule;
use Sitewarden\Report\EmailReport;
use Sitewarden\Report\ReportRefresher;
use Sitewarden\Rest\FindingsController;
use Sitewarden\Plugin;
use Sitewarden\Rest\ScanController;
use Sitewarden\Scanner\Scanner;
use Sitewarden\Users\LastLogin;

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
	 * @var array<string, callable[]>
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
				$this->actions[ $hook ][] = $callback;
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
		( $this->actions['admin_menu'][0] )();
		return $plugin;
	}

	public function testInit_Always_HooksTheParts(): void {
		$plugin = new Plugin( self::PLUGIN_FILE );

		$plugin->init();

		$classes = array_map(
			static function ( array $callbacks ): array {
				return array_map(
					static function ( $callback ): string {
						return is_array( $callback ) && is_object( $callback[0] ) ? get_class( $callback[0] ) . '::' . $callback[1] : 'other';
					},
					$callbacks
				);
			},
			$this->actions
		);
		self::assertSame(
			[
				'admin_menu'                          => [ Menu::class . '::register' ],
				'admin_init'                          => [ SettingsPage::class . '::register' ],
				'admin_post_sitewarden_test_provider' => [ SettingsPage::class . '::handle_test' ],
				'admin_enqueue_scripts'               => [ Plugin::class . '::enqueue_admin_assets' ],
				'rest_api_init'                       => [ ScanController::class . '::register_routes', FindingsController::class . '::register_routes' ],
				Scanner::TICK_HOOK                    => [ Scanner::class . '::handle_tick_event' ],
				Schedule::HOOK                        => [ Schedule::class . '::run' ],
				'add_option_sitewarden_settings'      => [ Schedule::class . '::sync' ],
				'update_option_sitewarden_settings'   => [ Schedule::class . '::sync', ReportRefresher::class . '::refresh' ],
				'sitewarden_scan_completed'           => [ EmailReport::class . '::maybe_send' ],
				'wp_login'                            => [ LastLogin::class . '::record' ],
			],
			$classes
		);
	}

	public function testActivate_NetworkWide_IsRefused(): void {
		$errors = $this->createMock( Errors::class );
		$errors->expects( self::once() )->method( 'wp_die' );
		ServiceFactory::set_custom_errors( $errors );

		Plugin::activate( true );
	}

	public function testActivate_SingleSite_IsAllowed(): void {
		$errors = $this->createMock( Errors::class );
		$errors->expects( self::never() )->method( 'wp_die' );
		ServiceFactory::set_custom_errors( $errors );

		Plugin::activate( false );
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

<?php declare( strict_types=1 );

namespace Sitewarden\Tests;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Plugin;

/**
 * Each WordPress function the plugin calls goes through a WPAL service; the tests swap the
 * services for mocks with ServiceFactory::set_custom_*() and check the calls.
 */
class PluginTest extends TestCase {
	private const PLUGIN_URL = 'https://example.com/wp-content/plugins/sitewarden/';

	/**
	 * A plugin directory with a build of assets: admin has a stylesheet and uses wp-i18n.
	 */
	private const PLUGIN_FILE = __DIR__ . '/../fixtures/plugin/sitewarden.php';

	/**
	 * @var Hooks&MockObject
	 */
	private $hooks;

	/**
	 * @var Assets&MockObject
	 */
	private $assets;

	protected function setUp(): void {
		$this->hooks  = $this->createMock( Hooks::class );
		$this->assets = $this->createMock( Assets::class );
		$plugins      = $this->createMock( Plugins::class );
		$plugins->method( 'plugin_dir_url' )->willReturn( self::PLUGIN_URL );

		ServiceFactory::set_custom_hooks( $this->hooks );
		ServiceFactory::set_custom_assets( $this->assets );
		ServiceFactory::set_custom_plugins( $plugins );
	}

	public function testInit_Always_HooksAssetLoading(): void {
		$plugin = new Plugin( self::PLUGIN_FILE );
		$added  = [];
		$this->hooks->method( 'add_action' )->willReturnCallback(
			function ( string $hook, callable $callback ) use ( &$added ): bool {
				$added[ $hook ] = $callback;
				return true;
			}
		);

		$plugin->init();

		self::assertEquals(
			[
				'admin_enqueue_scripts' => [ $plugin, 'enqueue_admin_assets' ],
			],
			$added
		);
	}

	public function testEnqueueAdminAssets_WithStylesheet_EnqueuesStyleWithBuildVersion(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_enqueue_style' )
			->with( 'sitewarden-admin', self::PLUGIN_URL . 'assets/build/admin.css', [], 'admin-hash' );

		( new Plugin( self::PLUGIN_FILE ) )->enqueue_admin_assets();
	}

	public function testEnqueueAdminAssets_WithStylesheet_UsesRtlStylesheetForRtlLanguages(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_style_add_data' )
			->with( 'sitewarden-admin', 'rtl', 'replace' );

		( new Plugin( self::PLUGIN_FILE ) )->enqueue_admin_assets();
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

		( new Plugin( self::PLUGIN_FILE ) )->enqueue_admin_assets();
	}

	public function testEnqueueAdminAssets_ScriptUsesI18n_LoadsScriptTranslations(): void {
		$this->assets->expects( self::once() )
			->method( 'wp_set_script_translations' )
			->with( 'sitewarden-admin', 'sitewarden' );

		( new Plugin( self::PLUGIN_FILE ) )->enqueue_admin_assets();
	}

	public function testEnqueueAdminAssets_NotBuilt_EnqueuesNothing(): void {
		$this->assets->expects( self::never() )->method( 'wp_enqueue_style' );
		$this->assets->expects( self::never() )->method( 'wp_enqueue_script' );

		( new Plugin( __DIR__ . '/sitewarden.php' ) )->enqueue_admin_assets();
	}
}

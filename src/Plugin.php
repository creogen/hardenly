<?php declare( strict_types=1 );

namespace Hardenly;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Admin\DashboardPage;
use Hardenly\Admin\Menu;
use Hardenly\Admin\NetworkPage;
use Hardenly\Admin\SettingsPage;
use Hardenly\Cron\Schedule;
use Hardenly\Report\EmailReport;
use Hardenly\Report\ReportRefresher;
use Hardenly\Report\Summary;
use Hardenly\Rest\FindingsController;
use Hardenly\Rest\ReportPayload;
use Hardenly\Rest\ScanController;
use Hardenly\Scanner\Checks\BuiltInChecks;
use Hardenly\Scanner\CheckRegistry;
use Hardenly\Scanner\Scanner;
use Hardenly\Scanner\Score;
use Hardenly\Storage\Lock;
use Hardenly\Storage\ReportStore;
use Hardenly\Storage\RunStore;
use Hardenly\Storage\Settings;
use Hardenly\Users\LastLogin;
use Hardenly\Vulnerability\PatchstackProvider;
use Hardenly\Vulnerability\ProviderRegistry;
use Hardenly\Vulnerability\WpscanProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 *
 * WordPress functions are called through WPAL services, so tests can replace them with
 * mocks: see tests/unit/PluginTest.php.
 */
class Plugin {
	/**
	 * Keep in sync with the plugin header, readme.txt and package.json: `make version-check`.
	 */
	public const VERSION = '1.0.0';

	public const TEXT_DOMAIN = 'hardenly';

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * Set by init().
	 *
	 * @var Menu|null
	 */
	private $menu = null;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->hooks       = ServiceFactory::create_hooks();
		$this->assets      = ServiceFactory::create_assets();
		$this->plugins     = ServiceFactory::create_plugins();
	}

	/**
	 * Creates the plugin's objects and hooks them into WordPress.
	 */
	public function init(): void {
		$last_login = new LastLogin();
		$last_login->start();

		$settings      = new Settings();
		$providers     = new ProviderRegistry( [ new WpscanProvider(), new PatchstackProvider() ] );
		$reports       = new ReportStore();
		$summary       = new Summary( $this->score() );
		$scanner       = new Scanner(
			new CheckRegistry( BuiltInChecks::all( $last_login, $providers ) ),
			new RunStore(),
			$reports,
			$settings,
			new Lock( 'hardenly_lock' ),
			$summary
		);
		$schedule      = new Schedule( $scanner, $settings );
		$settings_page = new SettingsPage( $settings, $schedule, $providers );
		$scans         = new ScanController( $scanner, new ReportPayload( $reports, $settings ) );
		$refresher     = new ReportRefresher( $reports, $settings, $summary );
		$findings      = new FindingsController( $scans, $reports, $settings, $refresher );
		$email         = new EmailReport( $settings, $reports );
		$this->menu    = new Menu( new DashboardPage(), $settings_page );

		$this->hooks->add_action( 'admin_menu', [ $this->menu, 'register' ] );
		$this->hooks->add_action( 'admin_init', [ $settings_page, 'register' ] );
		$this->hooks->add_filter( 'option_page_capability_' . SettingsPage::GROUP, [ $settings_page, 'capability' ] );
		$this->hooks->add_action( 'network_admin_menu', [ new NetworkPage(), 'register' ] );
		$this->hooks->add_action( 'admin_post_' . SettingsPage::TEST_ACTION, [ $settings_page, 'handle_test' ] );
		$this->hooks->add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		$this->hooks->add_action( 'rest_api_init', [ $scans, 'register_routes' ] );
		$this->hooks->add_action( 'rest_api_init', [ $findings, 'register_routes' ] );
		$this->hooks->add_action( Scanner::TICK_HOOK, [ $scanner, 'handle_tick_event' ] );
		$this->hooks->add_action( Schedule::HOOK, [ $schedule, 'run' ] );
		$this->hooks->add_action( 'add_option_' . Settings::OPTION, [ $schedule, 'sync' ] );
		$this->hooks->add_action( 'update_option_' . Settings::OPTION, [ $schedule, 'sync' ] );
		$this->hooks->add_action( 'update_option_' . Settings::OPTION, [ $refresher, 'refresh' ] );
		$this->hooks->add_action( 'hardenly_scan_completed', [ $email, 'maybe_send' ] );
		$this->hooks->add_action( 'wp_login', [ $last_login, 'record' ], 10, 2 );
	}

	/**
	 * Loads assets/build/admin.{js,css}, built from assets/src/admin, on the plugin's screens.
	 *
	 * @param string $hook_suffix Screen being loaded, passed by admin_enqueue_scripts.
	 */
	public function enqueue_admin_assets( $hook_suffix = '' ): void {
		if ( null === $this->menu || ! $this->menu->is_dashboard_screen( (string) $hook_suffix ) ) {
			return;
		}
		$this->enqueue_entry( 'admin' );
	}

	private function score(): Score {
		/**
		 * Filters the weights of the security score.
		 *
		 * The score starts at 100 and loses the severity's penalty for each problem that is not
		 * ignored; one check group can take off at most `group_cap`. Missing or non-numeric
		 * values keep their defaults and negative values count as 0.
		 *
		 * @since 1.0.0
		 *
		 * @param array $weights `severity` => penalty per problem by severity (`critical` 25,
		 *                       `high` 15, `medium` 8, `low` 3, `info` 0), `group_cap` => 40.
		 */
		$weights = $this->hooks->apply_filters( 'hardenly_score_weights', Score::DEFAULT_WEIGHTS );
		return new Score( is_array( $weights ) ? $weights : Score::DEFAULT_WEIGHTS );
	}

	/**
	 * Enqueues the script and stylesheet `npm run build` produced for an entry point.
	 *
	 * Dependencies and the version come from the generated <entry>.asset.php: importing
	 * `@wordpress/i18n` adds `wp-i18n`, and the version changes whenever the file does,
	 * so browsers never keep a stale copy.
	 *
	 * @param string $entry Entry point name in webpack.config.js.
	 */
	private function enqueue_entry( string $entry ): void {
		$build_dir = dirname( $this->plugin_file ) . '/assets/build/';
		$asset     = $build_dir . $entry . '.asset.php';
		if ( ! is_file( $asset ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $meta */
		$meta   = require $asset;
		$handle = self::TEXT_DOMAIN . '-' . $entry;
		$url    = $this->plugins->plugin_dir_url( $this->plugin_file ) . 'assets/build/';

		if ( is_file( $build_dir . $entry . '.css' ) ) {
			$this->assets->wp_enqueue_style( $handle, $url . $entry . '.css', [], $meta['version'] );
			// The build also writes <entry>-rtl.css, which WordPress loads for right-to-left languages.
			$this->assets->wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		$this->assets->wp_enqueue_script(
			$handle,
			$url . $entry . '.js',
			$meta['dependencies'],
			$meta['version'],
			[ 'in_footer' => true ]
		);

		if ( in_array( 'wp-i18n', $meta['dependencies'], true ) ) {
			$this->assets->wp_set_script_translations( $handle, self::TEXT_DOMAIN );
		}
	}
}

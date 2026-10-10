<?php declare( strict_types=1 );

namespace Sitewarden\Admin;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The top-level "Sitewarden" menu with Dashboard and Settings. Only users who can manage
 * options see it.
 */
class Menu {
	public const CAPABILITY = 'manage_options';
	public const SLUG       = 'sitewarden';

	/**
	 * @var DashboardPage
	 */
	private $dashboard;

	/**
	 * @var SettingsPage
	 */
	private $settings;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * Hook suffixes of the dashboard screen, set once the menu is registered.
	 *
	 * @var string[]
	 */
	private $hook_suffixes = [];

	public function __construct( DashboardPage $dashboard, SettingsPage $settings ) {
		$this->dashboard = $dashboard;
		$this->settings  = $settings;
		$this->plugins   = ServiceFactory::create_plugins();
	}

	public function register(): void {
		$top = $this->plugins->add_menu_page(
			__( 'Sitewarden', 'sitewarden' ),
			__( 'Sitewarden', 'sitewarden' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this->dashboard, 'render' ],
			'dashicons-shield'
		);

		// Names the first submenu item "Dashboard" instead of repeating "Sitewarden".
		$dashboard = $this->plugins->add_submenu_page(
			self::SLUG,
			__( 'Sitewarden', 'sitewarden' ),
			__( 'Dashboard', 'sitewarden' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this->dashboard, 'render' ]
		);

		$this->plugins->add_submenu_page(
			self::SLUG,
			__( 'Sitewarden Settings', 'sitewarden' ),
			__( 'Settings', 'sitewarden' ),
			self::CAPABILITY,
			SettingsPage::SLUG,
			[ $this->settings, 'render' ]
		);

		$this->hook_suffixes = [ $top ];
		if ( is_string( $dashboard ) ) {
			$this->hook_suffixes[] = $dashboard;
		}
	}

	/**
	 * Whether a screen, by the hook suffix admin_enqueue_scripts passes, is the dashboard: the
	 * only screen that loads the plugin's script. Settings is a plain form.
	 */
	public function is_dashboard_screen( string $hook_suffix ): bool {
		return in_array( $hook_suffix, $this->hook_suffixes, true );
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Loads WordPress admin functions that cron and REST requests do not load.
 */
class AdminIncludes {
	/**
	 * get_plugins() and is_plugin_active().
	 */
	public static function plugins(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}

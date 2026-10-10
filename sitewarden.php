<?php
/**
 * Plugin Name:       Sitewarden
 * Plugin URI:        https://github.com/creogen/sitewarden
 * Description:       Security scanner for your WordPress website.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Dmitry Merkushin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sitewarden
 *
 * @package Sitewarden
 */

namespace Sitewarden;

defined( 'ABSPATH' ) || exit;

// Release builds ship dependencies prefixed by wp-scoper in vendor-prefixed/;
// a development checkout uses the regular Composer autoloader.
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
} else {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_action( 'init', [ new Plugin( __FILE__ ), 'init' ] );

// Single sites only: network activation is refused (see Plugin::activate()).
register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );

// Scheduled scans and pending scan batches stop with the plugin.
register_deactivation_hook( __FILE__, [ Cron\Schedule::class, 'clear_all' ] );

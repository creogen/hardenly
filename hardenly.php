<?php
/**
 * Plugin Name:       Hardenly
 * Plugin URI:        https://github.com/creogen/hardenly
 * Description:       Security scanner for your WordPress website.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Dmitry Merkushin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hardenly
 *
 * @package Hardenly
 */

namespace Hardenly;

defined( 'ABSPATH' ) || exit;

// Release builds ship dependencies prefixed by wp-scoper in vendor-prefixed/;
// a development checkout uses the regular Composer autoloader.
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
} else {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_action( 'init', [ new Plugin( __FILE__ ), 'init' ] );

// Scheduled scans and pending scan batches stop with the plugin, on every site when it was network-active.
register_deactivation_hook( __FILE__, [ Cron\Schedule::class, 'deactivate' ] );

<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes everything it stored.
 *
 * @package Hardenly
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'hardenly_settings', 'hardenly_latest_report', 'hardenly_report_history', 'hardenly_run_state', 'hardenly_lock', 'hardenly_tracking_since', 'hardenly_vulnerability_cache' ] as $hardenly_option ) {
	delete_option( $hardenly_option );
}

delete_transient( 'hardenly_core_checksums' );
delete_transient( 'hardenly_provider_test' );

// Last login dates of all users.
delete_metadata( 'user', 0, 'hardenly_last_login', '', true );

wp_unschedule_hook( 'hardenly_scan_tick' );
wp_unschedule_hook( 'hardenly_scheduled_scan' );

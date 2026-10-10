<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes everything it stored.
 *
 * @package Sitewarden
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'sitewarden_settings', 'sitewarden_latest_report', 'sitewarden_report_history', 'sitewarden_run_state', 'sitewarden_lock', 'sitewarden_tracking_since', 'sitewarden_vulnerability_cache' ] as $sitewarden_option ) {
	delete_option( $sitewarden_option );
}

delete_transient( 'sitewarden_core_checksums' );
delete_transient( 'sitewarden_provider_test' );

// Last login dates of all users.
delete_metadata( 'user', 0, 'sitewarden_last_login', '', true );

wp_unschedule_hook( 'sitewarden_scan_tick' );
wp_unschedule_hook( 'sitewarden_scheduled_scan' );

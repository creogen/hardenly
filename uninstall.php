<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes everything it stored.
 *
 * @package Sitewarden
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'sitewarden_settings', 'sitewarden_latest_report', 'sitewarden_report_history', 'sitewarden_run_state', 'sitewarden_lock' ] as $sitewarden_option ) {
	delete_option( $sitewarden_option );
}

wp_unschedule_hook( 'sitewarden_scan_tick' );

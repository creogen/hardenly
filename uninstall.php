<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes everything it stored,
 * on every site of a network.
 *
 * @package Hardenly
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes Hardenly's options, transients and scheduled events from the current site.
 */
function hardenly_uninstall_site(): void {
	foreach ( [ 'hardenly_settings', 'hardenly_latest_report', 'hardenly_report_history', 'hardenly_run_state', 'hardenly_lock', 'hardenly_tracking_since', 'hardenly_vulnerability_cache' ] as $hardenly_option ) {
		delete_option( $hardenly_option );
	}

	delete_transient( 'hardenly_core_checksums' );
	delete_transient( 'hardenly_provider_test' );

	wp_unschedule_hook( 'hardenly_scan_tick' );
	wp_unschedule_hook( 'hardenly_scheduled_scan' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) as $hardenly_site_id ) {
		switch_to_blog( (int) $hardenly_site_id );
		hardenly_uninstall_site();
		restore_current_blog();
	}
} else {
	hardenly_uninstall_site();
}

// Last login dates: user meta is shared by all sites.
delete_metadata( 'user', 0, 'hardenly_last_login', '', true );

<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Remove everything the plugin stored: options, transients, custom tables, user meta,
 * scheduled events. WordPress.org reviewers expect a plugin to clean up after itself.
 * For example:
 *
 *     delete_option( 'sitewarden_settings' );
 *     delete_transient( 'sitewarden_cache' );
 *
 * @package Sitewarden
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

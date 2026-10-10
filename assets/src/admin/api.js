/**
 * Calls to the plugin's REST routes (src/Rest/ScanController.php).
 */
import apiFetch from '@wordpress/api-fetch';

const BASE = '/hardenly/v1';

export const getReport = () => apiFetch( { path: `${ BASE }/report` } );

export const getStatus = () => apiFetch( { path: `${ BASE }/scan` } );

export const startScan = () =>
	apiFetch( { path: `${ BASE }/scan`, method: 'POST' } );

export const stopScan = () =>
	apiFetch( { path: `${ BASE }/scan`, method: 'DELETE' } );

/**
 * Hides a finding from the score and counts; returns the updated report data.
 *
 * @param {string} id Finding id.
 */
export const ignoreFinding = ( id ) =>
	apiFetch( {
		path: `${ BASE }/findings/ignore`,
		method: 'POST',
		data: { id },
	} );

/**
 * Shows an ignored finding again; returns the updated report data.
 *
 * @param {string} id Finding id.
 */
export const restoreFinding = ( id ) =>
	apiFetch( {
		path: `${ BASE }/findings/restore`,
		method: 'POST',
		data: { id },
	} );

/**
 * Adds a file to "Files to skip"; returns the updated report data.
 *
 * @param {string} path Path relative to the WordPress folder.
 */
export const skipFile = ( path ) =>
	apiFetch( {
		path: `${ BASE }/files/skip`,
		method: 'POST',
		data: { path },
	} );

/**
 * Runs a short batch of the scan and returns its status.
 */
export const tick = () =>
	apiFetch( { path: `${ BASE }/scan/tick`, method: 'POST' } );

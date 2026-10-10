/**
 * Calls to the plugin's REST routes (src/Rest/ScanController.php).
 */
import apiFetch from '@wordpress/api-fetch';

const BASE = '/sitewarden/v1';

export const getReport = () => apiFetch( { path: `${ BASE }/report` } );

export const getStatus = () => apiFetch( { path: `${ BASE }/scan` } );

export const startScan = () =>
	apiFetch( { path: `${ BASE }/scan`, method: 'POST' } );

export const stopScan = () =>
	apiFetch( { path: `${ BASE }/scan`, method: 'DELETE' } );

/**
 * Runs a short batch of the scan and returns its status.
 */
export const tick = () =>
	apiFetch( { path: `${ BASE }/scan/tick`, method: 'POST' } );

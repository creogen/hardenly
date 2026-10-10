/**
 * Pure helpers for reading a report. Kept free of WordPress imports so they are easy to test.
 */

/** Most severe first, as in Result::SEVERITIES. */
export const SEVERITIES = [ 'critical', 'high', 'medium', 'low', 'info' ];

export const ACTIVE_STATES = [ 'queued', 'running' ];

/**
 * @param {Object} status Scan status from the REST API.
 * @return {boolean} Whether a scan is queued or running.
 */
export const isActive = ( status ) =>
	!! status && ACTIVE_STATES.includes( status.state );

/**
 * @param {Object} result Stored result.
 * @return {boolean} Whether it is a problem: a failure or a warning.
 */
export const isProblem = ( result ) =>
	result.status === 'fail' || result.status === 'warning';

/**
 * @param {number} score Score from 0 to 100.
 * @return {'good'|'attention'|'risk'} Band the score falls in.
 */
export const scoreLevel = ( score ) => {
	if ( score >= 90 ) {
		return 'good';
	}
	return score >= 60 ? 'attention' : 'risk';
};

/**
 * Problems grouped by severity, most severe first; empty groups are left out.
 *
 * @param {Object[]} results Report results.
 * @return {{severity: string, results: Object[]}[]} Groups.
 */
export const groupBySeverity = ( results ) =>
	SEVERITIES.map( ( severity ) => ( {
		severity,
		results: results.filter(
			( result ) =>
				isProblem( result ) &&
				! result.ignored &&
				result.severity === severity
		),
	} ) ).filter( ( group ) => group.results.length > 0 );

/**
 * Problems the user chose to ignore.
 *
 * @param {Object[]} results Report results.
 * @return {Object[]} Ignored problems.
 */
export const ignoredProblems = ( results ) =>
	results.filter( ( result ) => isProblem( result ) && result.ignored );

/** Checks whose details start with a file path that "Skip this file" can add to the settings. */
export const FILE_CHECKS = [
	'files.uploads_php',
	'files.backups',
	'code.suspicious',
];

/**
 * The file path at the start of a detail, such as "wp-content/x.php:12 eval(...)".
 *
 * @param {string} checkId Check the detail belongs to.
 * @param {string} detail  Detail text.
 * @return {string|null} Path, or null when the detail is not about a file.
 */
export const detailPath = ( checkId, detail ) => {
	if ( ! FILE_CHECKS.includes( checkId ) ) {
		return null;
	}
	const match = /^([^\s:]+)/.exec( detail );
	return match ? match[ 1 ] : null;
};

/**
 * Results that are not problems but worth reading: notes, and checks that could not run.
 *
 * @param {Object[]} results Report results.
 * @return {Object[]} Notes first, then skipped checks.
 */
export const notes = ( results ) => [
	...results.filter( ( result ) => result.status === 'info' ),
	...results.filter( ( result ) => result.status === 'skipped' ),
];

/**
 * Change in score since the scan before the latest one.
 *
 * @param {Object[]} history Scan summaries, newest first.
 * @return {number|null} Difference, or null with fewer than two scans.
 */
export const scoreChange = ( history ) =>
	history.length < 2 ? null : history[ 0 ].score - history[ 1 ].score;

/**
 * @param {Object} status Scan status.
 * @return {number} Progress from 0 to 100.
 */
export const progressPercent = ( status ) =>
	Math.max( 0, Math.min( 100, status?.progress?.percent ?? 0 ) );

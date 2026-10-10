import {
	createElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { dateI18n, getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import * as api from './api';
import { isActive } from './report';
import FirstRun from './components/FirstRun';
import Findings from './components/Findings';
import Icon from './components/Icon';
import Progress from './components/Progress';
import Summary from './components/Summary';

/** Pause between ticks while a scan runs, in milliseconds. */
const POLL_DELAY = 1000;

const errorMessage = ( error ) =>
	error?.message ||
	__( 'Something went wrong. Reload the page and try again.', 'sitewarden' );

function Notice( { status } ) {
	if ( status.state === 'failed' ) {
		return (
			<div className="notice notice-error inline">
				<p>
					{ __(
						'The last scan stopped making progress and was cancelled. If this keeps happening, WP-Cron may be disabled on this site; keep this page open while the scan runs.',
						'sitewarden'
					) }
				</p>
			</div>
		);
	}
	if ( status.state === 'stopped' ) {
		return (
			<div className="notice notice-warning inline">
				<p>{ __( 'The last scan was stopped.', 'sitewarden' ) }</p>
			</div>
		);
	}
	return null;
}

export default function App() {
	const [ data, setData ] = useState( null );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const loadReport = useCallback(
		() => api.getReport().then( setData ).catch( setError ),
		[]
	);

	useEffect( () => {
		Promise.all( [ api.getReport(), api.getStatus() ] )
			.then( ( [ report, scan ] ) => {
				setData( report );
				setStatus( scan );
			} )
			.catch( setError );
	}, [] );

	// While a scan runs, keep asking for the next batch until it is done.
	const active = isActive( status );
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		let cancelled = false;
		let timer;
		const poll = () => {
			api.tick()
				.then( ( next ) => {
					if ( cancelled ) {
						return;
					}
					setStatus( next );
					if ( isActive( next ) ) {
						timer = setTimeout( poll, POLL_DELAY );
					} else {
						loadReport();
					}
				} )
				.catch( ( e ) => ! cancelled && setError( e ) );
		};
		timer = setTimeout( poll, POLL_DELAY );
		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ active, loadReport ] );

	const run = ( request ) => () => {
		setBusy( true );
		setError( null );
		request()
			.then( setStatus )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};
	const start = run( api.startScan );
	const stop = run( api.stopScan );

	// Ignoring, restoring and skipping return the updated report data.
	const change = ( request ) => ( value ) => {
		setBusy( true );
		setError( null );
		request( value )
			.then( setData )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};
	const actions = {
		ignore: change( api.ignoreFinding ),
		restore: change( api.restoreFinding ),
		skip: change( api.skipFile ),
	};

	if ( error && ! data ) {
		return (
			<div className="notice notice-error inline">
				<p>{ errorMessage( error ) }</p>
			</div>
		);
	}
	if ( ! data || ! status ) {
		return <p>{ __( 'Loading…', 'sitewarden' ) }</p>;
	}

	const {
		report,
		history,
		groups,
		skip_files: skipFiles = [],
		vulnerability_lookup: lookupReady = false,
	} = data;

	return (
		<div className="sitewarden">
			<div className="sitewarden-header">
				<h1>{ __( 'Sitewarden', 'sitewarden' ) }</h1>
				{ report && ! active && (
					<button
						type="button"
						className="button"
						onClick={ start }
						disabled={ busy }
					>
						<Icon name="refresh" />
						{ __( 'Run scan', 'sitewarden' ) }
					</button>
				) }
				{ report && (
					<span className="sitewarden-muted">
						{ sprintf(
							/* translators: %s: date and time of the last scan. */
							__( 'Last scan: %s', 'sitewarden' ),
							dateI18n(
								getSettings().formats.datetime,
								report.finished_at * 1000
							)
						) }
					</span>
				) }
			</div>

			{ error && (
				<div className="notice notice-error inline">
					<p>{ errorMessage( error ) }</p>
				</div>
			) }
			<Notice status={ status } />
			{ report && ! lookupReady && (
				<div className="notice notice-info inline">
					<p>
						<strong>
							{ __(
								'Vulnerability lookup is not configured.',
								'sitewarden'
							) }
						</strong>{ ' ' }
						{ __(
							'Version checks work offline. To match installed plugins and themes against known vulnerabilities, connect WPScan or Patchstack.',
							'sitewarden'
						) }{ ' ' }
						<a href="admin.php?page=sitewarden-settings">
							{ __( 'Set up in Settings', 'sitewarden' ) }
						</a>
					</p>
				</div>
			) }

			{ active && (
				<Progress status={ status } onStop={ stop } busy={ busy } />
			) }
			{ ! active && ! report && (
				<FirstRun onStart={ start } busy={ busy } />
			) }

			{ report && (
				<div
					className={ `sitewarden-results${
						active ? ' sitewarden-stale' : ''
					}` }
				>
					<Summary report={ report } history={ history } />
					<Findings
						report={ report }
						groups={ groups }
						skipFiles={ skipFiles }
						actions={ active ? null : actions }
						busy={ busy }
					/>
				</div>
			) }
		</div>
	);
}

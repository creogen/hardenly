import { createElement, Fragment } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { detailPath, groupBySeverity, ignoredProblems, notes } from '../report';
import Icon from './Icon';

const SEVERITY_LABELS = {
	critical: __( 'Critical', 'sitewarden' ),
	high: __( 'High', 'sitewarden' ),
	medium: __( 'Medium', 'sitewarden' ),
	low: __( 'Low', 'sitewarden' ),
	info: __( 'Info', 'sitewarden' ),
};

/** Severities whose findings start expanded. */
const OPEN_SEVERITIES = [ 'critical', 'high' ];

function Detail( { result, detail, skipFiles, actions, busy } ) {
	const path = actions && detailPath( result.check_id, detail );
	let action = null;
	if ( path && skipFiles.includes( path ) ) {
		action = (
			<span className="sitewarden-muted">
				{ __( 'Skipped from the next scan', 'sitewarden' ) }
			</span>
		);
	} else if ( path ) {
		action = (
			<button
				type="button"
				className="button-link"
				onClick={ () => actions.skip( path ) }
				disabled={ busy }
				aria-label={ sprintf(
					/* translators: %s: file path. */
					__( 'Skip %s in future scans', 'sitewarden' ),
					path
				) }
			>
				{ __( 'Skip this file', 'sitewarden' ) }
			</button>
		);
	}

	return (
		<li>
			<code>{ detail }</code> { action }
		</li>
	);
}

function Finding( {
	result,
	groupLabel,
	open,
	skipFiles = [],
	actions = null,
	busy = false,
} ) {
	const hidden = result.details_total - result.details.length;

	return (
		<details className="sitewarden-card sitewarden-finding" open={ open }>
			<summary>
				<span className="sitewarden-finding__title">
					{ result.message }
				</span>
				{ result.status === 'skipped' && (
					<span className="sitewarden-tag">
						{ __( 'Not checked', 'sitewarden' ) }
					</span>
				) }
				{ groupLabel && (
					<span className="sitewarden-tag">{ groupLabel }</span>
				) }
			</summary>
			<div className="sitewarden-finding__body">
				{ result.description && <p>{ result.description }</p> }
				{ result.details.length > 0 && (
					<ul className="sitewarden-finding__details">
						{ result.details.map( ( detail, index ) => (
							<Detail
								key={ index }
								result={ result }
								detail={ detail }
								skipFiles={ skipFiles }
								actions={ result.ignored ? null : actions }
								busy={ busy }
							/>
						) ) }
						{ hidden > 0 && (
							<li className="sitewarden-muted">
								{ sprintf(
									/* translators: %d: number of items not shown. */
									_n(
										'and %d more',
										'and %d more',
										hidden,
										'sitewarden'
									),
									hidden
								) }
							</li>
						) }
					</ul>
				) }
				{ result.remediation && (
					<div className="sitewarden-finding__fix">
						<strong>{ __( 'How to fix:', 'sitewarden' ) }</strong>{ ' ' }
						{ result.remediation }
					</div>
				) }
				{ actions && (
					<div className="sitewarden-finding__actions">
						{ result.ignored ? (
							<button
								type="button"
								className="button-link"
								onClick={ () => actions.restore( result.id ) }
								disabled={ busy }
							>
								{ __( 'Show again', 'sitewarden' ) }
							</button>
						) : (
							<button
								type="button"
								className="button-link"
								onClick={ () => actions.ignore( result.id ) }
								disabled={ busy }
							>
								{ __( 'Ignore this finding', 'sitewarden' ) }
							</button>
						) }
					</div>
				) }
			</div>
		</details>
	);
}

function Section( { id, title, icon, className = '', children } ) {
	return (
		<section
			className="sitewarden-findings"
			aria-labelledby={ `sitewarden-findings-${ id }` }
		>
			<h2
				id={ `sitewarden-findings-${ id }` }
				className={ `sitewarden-findings__title ${ className }` }
			>
				<Icon name={ icon } />
				{ title }
			</h2>
			{ children }
		</section>
	);
}

export default function Findings( {
	report,
	groups,
	skipFiles = [],
	actions = null,
	busy = false,
} ) {
	const bySeverity = groupBySeverity( report.results );
	const ignored = ignoredProblems( report.results );
	const notesList = notes( report.results );
	const groupOf = ( result ) =>
		groups[ report.checks[ result.check_id ]?.group ] ??
		report.checks[ result.check_id ]?.group;

	return (
		<>
			{ bySeverity.length === 0 && (
				<div className="notice notice-success inline">
					<p>
						{ __(
							'No problems found. Keep WordPress, plugins and themes up to date and scan regularly.',
							'sitewarden'
						) }
					</p>
				</div>
			) }
			{ bySeverity.map( ( { severity, results } ) => (
				<Section
					key={ severity }
					id={ severity }
					title={ SEVERITY_LABELS[ severity ] }
					icon={ severity }
					className={ `sitewarden-severity--${ severity }` }
				>
					{ results.map( ( result, index ) => (
						<Finding
							key={ `${ result.id }:${ index }` }
							result={ result }
							groupLabel={ groupOf( result ) }
							skipFiles={ skipFiles }
							actions={ actions }
							busy={ busy }
							open={ OPEN_SEVERITIES.includes( severity ) }
						/>
					) ) }
				</Section>
			) ) }
			{ notesList.length > 0 && (
				<Section
					id="notes"
					title={ __( 'Notes', 'sitewarden' ) }
					icon="info"
				>
					{ notesList.map( ( result, index ) => (
						<Finding
							key={ `${ result.id }:${ index }` }
							result={ result }
							groupLabel={ groupOf( result ) }
							actions={ null }
							open={ false }
						/>
					) ) }
				</Section>
			) }
			{ ignored.length > 0 && (
				<Section
					id="ignored"
					title={ sprintf(
						/* translators: %d: number of ignored findings. */
						__( 'Ignored (%d)', 'sitewarden' ),
						ignored.length
					) }
					icon="pass"
				>
					<p className="sitewarden-muted">
						{ __(
							'Ignored findings do not count in the score, the problem counts or the email.',
							'sitewarden'
						) }
					</p>
					{ ignored.map( ( result, index ) => (
						<Finding
							key={ `${ result.id }:${ index }` }
							result={ result }
							groupLabel={ groupOf( result ) }
							skipFiles={ skipFiles }
							actions={ actions }
							busy={ busy }
							open={ false }
						/>
					) ) }
				</Section>
			) }
		</>
	);
}

import { createElement, Fragment } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { detailPath, groupBySeverity, ignoredProblems, notes } from '../report';
import Icon from './Icon';

const SEVERITY_LABELS = {
	critical: __( 'Critical', 'hardenly' ),
	high: __( 'High', 'hardenly' ),
	medium: __( 'Medium', 'hardenly' ),
	low: __( 'Low', 'hardenly' ),
	info: __( 'Info', 'hardenly' ),
};

/** Severities whose findings start expanded. */
const OPEN_SEVERITIES = [ 'critical', 'high' ];

function Detail( { result, detail, skipFiles, actions, busy } ) {
	const path = actions && detailPath( result.check_id, detail );
	let action = null;
	if ( path && skipFiles.includes( path ) ) {
		action = (
			<span className="hardenly-muted">
				{ __( 'Skipped from the next scan', 'hardenly' ) }
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
					__( 'Skip %s in future scans', 'hardenly' ),
					path
				) }
			>
				{ __( 'Skip this file', 'hardenly' ) }
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
		<details className="hardenly-card hardenly-finding" open={ open }>
			<summary>
				<span className="hardenly-finding__title">
					{ result.message }
				</span>
				{ result.status === 'skipped' && (
					<span className="hardenly-tag">
						{ __( 'Not checked', 'hardenly' ) }
					</span>
				) }
				{ groupLabel && (
					<span className="hardenly-tag">{ groupLabel }</span>
				) }
			</summary>
			<div className="hardenly-finding__body">
				{ result.description && <p>{ result.description }</p> }
				{ result.details.length > 0 && (
					<ul className="hardenly-finding__details">
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
							<li className="hardenly-muted">
								{ sprintf(
									/* translators: %d: number of items not shown. */
									_n(
										'and %d more',
										'and %d more',
										hidden,
										'hardenly'
									),
									hidden
								) }
							</li>
						) }
					</ul>
				) }
				{ result.remediation && (
					<div className="hardenly-finding__fix">
						<strong>{ __( 'How to fix:', 'hardenly' ) }</strong>{ ' ' }
						{ result.remediation }
					</div>
				) }
				{ actions && (
					<div className="hardenly-finding__actions">
						{ result.ignored ? (
							<button
								type="button"
								className="button-link"
								onClick={ () => actions.restore( result.id ) }
								disabled={ busy }
							>
								{ __( 'Show again', 'hardenly' ) }
							</button>
						) : (
							<button
								type="button"
								className="button-link"
								onClick={ () => actions.ignore( result.id ) }
								disabled={ busy }
							>
								{ __( 'Ignore this finding', 'hardenly' ) }
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
			className="hardenly-findings"
			aria-labelledby={ `hardenly-findings-${ id }` }
		>
			<h2
				id={ `hardenly-findings-${ id }` }
				className={ `hardenly-findings__title ${ className }` }
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
							'hardenly'
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
					className={ `hardenly-severity--${ severity }` }
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
					title={ __( 'Notes', 'hardenly' ) }
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
						__( 'Ignored (%d)', 'hardenly' ),
						ignored.length
					) }
					icon="pass"
				>
					<p className="hardenly-muted">
						{ __(
							'Ignored findings do not count in the score, the problem counts or the email.',
							'hardenly'
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

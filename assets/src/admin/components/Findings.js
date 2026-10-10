import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { groupBySeverity } from '../report';
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

function Finding( { result, groupLabel, open } ) {
	const hidden = result.details_total - result.details.length;

	return (
		<details className="sitewarden-card sitewarden-finding" open={ open }>
			<summary>
				<span className="sitewarden-finding__title">
					{ result.message }
				</span>
				{ groupLabel && (
					<span className="sitewarden-tag">{ groupLabel }</span>
				) }
			</summary>
			<div className="sitewarden-finding__body">
				{ result.description && <p>{ result.description }</p> }
				{ result.details.length > 0 && (
					<ul className="sitewarden-finding__details">
						{ result.details.map( ( detail, index ) => (
							<li key={ index }>
								<code>{ detail }</code>
							</li>
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
			</div>
		</details>
	);
}

export default function Findings( { report, groups } ) {
	const bySeverity = groupBySeverity( report.results );
	const groupOf = ( result ) =>
		groups[ report.checks[ result.check_id ]?.group ] ??
		report.checks[ result.check_id ]?.group;

	if ( bySeverity.length === 0 ) {
		return (
			<div className="notice notice-success inline">
				<p>
					{ __(
						'No problems found. Keep WordPress, plugins and themes up to date and scan regularly.',
						'sitewarden'
					) }
				</p>
			</div>
		);
	}

	return bySeverity.map( ( { severity, results } ) => (
		<section
			key={ severity }
			className="sitewarden-findings"
			aria-labelledby={ `sitewarden-findings-${ severity }` }
		>
			<h2
				id={ `sitewarden-findings-${ severity }` }
				className={ `sitewarden-findings__title sitewarden-severity--${ severity }` }
			>
				<Icon name={ severity } />
				{ SEVERITY_LABELS[ severity ] }
			</h2>
			{ results.map( ( result, index ) => (
				<Finding
					key={ `${ result.check_id }:${ result.key }:${ index }` }
					result={ result }
					groupLabel={ groupOf( result ) }
					open={ OPEN_SEVERITIES.includes( severity ) }
				/>
			) ) }
		</section>
	) );
}

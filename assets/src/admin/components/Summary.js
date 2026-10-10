import { createElement } from '@wordpress/element';
import { dateI18n, getSettings } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import { scoreChange, scoreLevel } from '../report';
import Icon from './Icon';

const RADIUS = 50;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const LEVEL_LABELS = {
	good: __( 'Good', 'sitewarden' ),
	attention: __( 'Needs attention', 'sitewarden' ),
	risk: __( 'At risk', 'sitewarden' ),
};

const COUNT_LABELS = {
	critical: __( 'Critical', 'sitewarden' ),
	high: __( 'High', 'sitewarden' ),
	medium: __( 'Medium', 'sitewarden' ),
	low: __( 'Low', 'sitewarden' ),
};

// Date and time: several scans on one day are common.
const formatDate = ( timestamp ) =>
	dateI18n( getSettings().formats.datetime, timestamp * 1000 );

function ScoreChange( { change } ) {
	if ( change === null ) {
		return null;
	}
	if ( change === 0 ) {
		return (
			<span className="sitewarden-muted">
				{ __( 'No change since the previous scan', 'sitewarden' ) }
			</span>
		);
	}
	const text =
		change > 0
			? sprintf(
					/* translators: %d: points the score went up. */
					__( 'Up %d since the previous scan', 'sitewarden' ),
					change
				)
			: sprintf(
					/* translators: %d: points the score went down. */
					__( 'Down %d since the previous scan', 'sitewarden' ),
					-change
				);
	return (
		<span
			className={ `sitewarden-change sitewarden-change--${
				change > 0 ? 'up' : 'down'
			}` }
		>
			<Icon name={ change > 0 ? 'up' : 'down' } size={ 14 } />
			{ text }
		</span>
	);
}

export default function Summary( { report, history } ) {
	const level = scoreLevel( report.score );
	const dash = ( report.score / 100 ) * CIRCUMFERENCE;

	return (
		<div className="sitewarden-summary">
			<section
				className="sitewarden-card sitewarden-score"
				aria-labelledby="sitewarden-score-title"
			>
				<svg
					width="120"
					height="120"
					viewBox="0 0 120 120"
					role="img"
					aria-label={ sprintf(
						/* translators: %d: security score. */
						__( 'Score %d out of 100', 'sitewarden' ),
						report.score
					) }
				>
					<circle
						className="sitewarden-score__track"
						cx="60"
						cy="60"
						r={ RADIUS }
					/>
					<circle
						className={ `sitewarden-score__value sitewarden-score__value--${ level }` }
						cx="60"
						cy="60"
						r={ RADIUS }
						strokeDasharray={ `${ dash } ${ CIRCUMFERENCE }` }
						transform="rotate(-90 60 60)"
					/>
					<text
						x="60"
						y="66"
						textAnchor="middle"
						className="sitewarden-score__number"
					>
						{ report.score }
					</text>
					<text
						x="60"
						y="84"
						textAnchor="middle"
						className="sitewarden-score__of"
					>
						{ __( 'of 100', 'sitewarden' ) }
					</text>
				</svg>
				<div className="sitewarden-score__text">
					<h2 id="sitewarden-score-title">
						{ sprintf(
							/* translators: %s: score band, e.g. "Needs attention". */
							__( 'Security score: %s', 'sitewarden' ),
							LEVEL_LABELS[ level ]
						) }
					</h2>
					<ScoreChange change={ scoreChange( history ) } />
					<span className="sitewarden-muted sitewarden-small">
						{ __(
							'The score is an indicator, not a guarantee that the site is secure.',
							'sitewarden'
						) }
					</span>
				</div>
			</section>

			<section
				className="sitewarden-card"
				aria-labelledby="sitewarden-counts-title"
			>
				<h2 id="sitewarden-counts-title">
					{ __( 'Findings', 'sitewarden' ) }
				</h2>
				<dl className="sitewarden-counts">
					{ Object.keys( COUNT_LABELS ).map( ( severity ) => (
						<div
							key={ severity }
							className={ `sitewarden-counts__item sitewarden-severity--${
								report.counts[ severity ] ? severity : 'none'
							}` }
						>
							<dt>{ COUNT_LABELS[ severity ] }</dt>
							<dd>{ report.counts[ severity ] ?? 0 }</dd>
						</div>
					) ) }
				</dl>
				<p className="sitewarden-muted">
					{ sprintf(
						/* translators: %d: number of checks that passed. */
						_n(
							'%d check passed',
							'%d checks passed',
							report.passed,
							'sitewarden'
						),
						report.passed
					) }
				</p>
			</section>

			{ history.length > 1 && (
				<section
					className="sitewarden-card"
					aria-labelledby="sitewarden-history-title"
				>
					<h2 id="sitewarden-history-title">
						{ __( 'Recent scans', 'sitewarden' ) }
					</h2>
					<table className="sitewarden-history">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Date', 'sitewarden' ) }
								</th>
								<th scope="col">
									{ __( 'Score', 'sitewarden' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ history.slice( 0, 5 ).map( ( item ) => (
								<tr key={ item.id }>
									<td>{ formatDate( item.finished_at ) }</td>
									<td>{ item.score }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</section>
			) }
		</div>
	);
}

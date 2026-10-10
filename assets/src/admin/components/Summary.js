import { createElement } from '@wordpress/element';
import { dateI18n, getSettings } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import { scoreChange, scoreLevel } from '../report';
import Icon from './Icon';

const RADIUS = 50;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const LEVEL_LABELS = {
	good: __( 'Good', 'hardenly' ),
	attention: __( 'Needs attention', 'hardenly' ),
	risk: __( 'At risk', 'hardenly' ),
};

const COUNT_LABELS = {
	critical: __( 'Critical', 'hardenly' ),
	high: __( 'High', 'hardenly' ),
	medium: __( 'Medium', 'hardenly' ),
	low: __( 'Low', 'hardenly' ),
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
			<span className="hardenly-muted">
				{ __( 'No change since the previous scan', 'hardenly' ) }
			</span>
		);
	}
	const text =
		change > 0
			? sprintf(
					/* translators: %d: points the score went up. */
					__( 'Up %d since the previous scan', 'hardenly' ),
					change
				)
			: sprintf(
					/* translators: %d: points the score went down. */
					__( 'Down %d since the previous scan', 'hardenly' ),
					-change
				);
	return (
		<span
			className={ `hardenly-change hardenly-change--${
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
		<div className="hardenly-summary">
			<section
				className="hardenly-card hardenly-score"
				aria-labelledby="hardenly-score-title"
			>
				<svg
					width="120"
					height="120"
					viewBox="0 0 120 120"
					role="img"
					aria-label={ sprintf(
						/* translators: %d: security score. */
						__( 'Score %d out of 100', 'hardenly' ),
						report.score
					) }
				>
					<circle
						className="hardenly-score__track"
						cx="60"
						cy="60"
						r={ RADIUS }
					/>
					<circle
						className={ `hardenly-score__value hardenly-score__value--${ level }` }
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
						className="hardenly-score__number"
					>
						{ report.score }
					</text>
					<text
						x="60"
						y="84"
						textAnchor="middle"
						className="hardenly-score__of"
					>
						{ __( 'of 100', 'hardenly' ) }
					</text>
				</svg>
				<div className="hardenly-score__text">
					<h2 id="hardenly-score-title">
						{ sprintf(
							/* translators: %s: score band, e.g. "Needs attention". */
							__( 'Security score: %s', 'hardenly' ),
							LEVEL_LABELS[ level ]
						) }
					</h2>
					<ScoreChange change={ scoreChange( history ) } />
					<span className="hardenly-muted hardenly-small">
						{ __(
							'The score is an indicator, not a guarantee that the site is secure.',
							'hardenly'
						) }
					</span>
				</div>
			</section>

			<section
				className="hardenly-card"
				aria-labelledby="hardenly-counts-title"
			>
				<h2 id="hardenly-counts-title">
					{ __( 'Findings', 'hardenly' ) }
				</h2>
				<dl className="hardenly-counts">
					{ Object.keys( COUNT_LABELS ).map( ( severity ) => (
						<div
							key={ severity }
							className={ `hardenly-counts__item hardenly-severity--${
								report.counts[ severity ] ? severity : 'none'
							}` }
						>
							<dt>{ COUNT_LABELS[ severity ] }</dt>
							<dd>{ report.counts[ severity ] ?? 0 }</dd>
						</div>
					) ) }
				</dl>
				<p className="hardenly-muted">
					{ sprintf(
						/* translators: %d: number of checks that passed. */
						_n(
							'%d check passed',
							'%d checks passed',
							report.passed,
							'hardenly'
						),
						report.passed
					) }
				</p>
			</section>

			{ history.length > 1 && (
				<section
					className="hardenly-card"
					aria-labelledby="hardenly-history-title"
				>
					<h2 id="hardenly-history-title">
						{ __( 'Recent scans', 'hardenly' ) }
					</h2>
					<table className="hardenly-history">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Date', 'hardenly' ) }
								</th>
								<th scope="col">
									{ __( 'Score', 'hardenly' ) }
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

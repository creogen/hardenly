import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { progressPercent } from '../report';

export default function Progress( { status, onStop, busy } ) {
	const percent = progressPercent( status );
	const { done, total, current } = status.progress;
	const title = current
		? sprintf(
				/* translators: %s: name of the check that is running. */
				__( 'Checking: %s', 'hardenly' ),
				current.title
			)
		: __( 'Starting the scan…', 'hardenly' );

	return (
		<section
			className="hardenly-card hardenly-progress"
			aria-labelledby="hardenly-progress-title"
		>
			<h2 id="hardenly-progress-title">{ title }</h2>
			<div
				className="hardenly-progress__bar"
				role="progressbar"
				aria-label={ __( 'Scan progress', 'hardenly' ) }
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow={ percent }
			>
				<div
					className="hardenly-progress__fill"
					style={ { width: `${ percent }%` } }
				/>
			</div>
			<p className="hardenly-muted">
				{ sprintf(
					/* translators: 1: percent done, 2: checks done, 3: total checks. */
					__( '%1$d%% · %2$d of %3$d checks done', 'hardenly' ),
					percent,
					done,
					total
				) }
			</p>
			<div className="hardenly-progress__footer">
				<p className="hardenly-muted">
					{ __(
						'The scan runs in the background in small steps. You can leave this page; results appear here when it finishes.',
						'hardenly'
					) }
				</p>
				<button
					type="button"
					className="button"
					onClick={ onStop }
					disabled={ busy }
				>
					{ __( 'Stop scan', 'hardenly' ) }
				</button>
			</div>
		</section>
	);
}

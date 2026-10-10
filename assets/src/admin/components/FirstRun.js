import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';

export default function FirstRun( { onStart, busy } ) {
	return (
		<section
			className="hardenly-card hardenly-first-run"
			aria-labelledby="hardenly-first-run-title"
		>
			<span className="hardenly-first-run__icon">
				<Icon name="shield" size={ 96 } />
			</span>
			<div className="hardenly-first-run__body">
				<h2 id="hardenly-first-run-title">
					{ __(
						'Check your site for security problems',
						'hardenly'
					) }
				</h2>
				<p>
					{ __(
						'Hardenly looks at your WordPress settings, users, plugins, themes and files, and tells you what to fix and how. It only reads; it never changes or deletes anything on your site.',
						'hardenly'
					) }
				</p>
				<ul>
					<li>
						{ __(
							'Works offline: nothing is sent outside your site unless you turn it on in Settings.',
							'hardenly'
						) }
					</li>
					<li>
						{ __(
							'Runs in small steps in the background, so it is safe on shared hosting.',
							'hardenly'
						) }
					</li>
				</ul>
				<p>
					<button
						type="button"
						className="button button-primary button-hero"
						onClick={ onStart }
						disabled={ busy }
					>
						{ __( 'Run first scan', 'hardenly' ) }
					</button>
				</p>
			</div>
		</section>
	);
}

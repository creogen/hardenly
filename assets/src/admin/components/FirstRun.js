import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';

export default function FirstRun( { onStart, busy } ) {
	return (
		<section
			className="sitewarden-card sitewarden-first-run"
			aria-labelledby="sitewarden-first-run-title"
		>
			<span className="sitewarden-first-run__icon">
				<Icon name="shield" size={ 96 } />
			</span>
			<div className="sitewarden-first-run__body">
				<h2 id="sitewarden-first-run-title">
					{ __(
						'Check your site for security problems',
						'sitewarden'
					) }
				</h2>
				<p>
					{ __(
						'Sitewarden looks at your WordPress settings, users, plugins, themes and files, and tells you what to fix and how. It only reads; it never changes or deletes anything on your site.',
						'sitewarden'
					) }
				</p>
				<ul>
					<li>
						{ __(
							'Works offline: nothing is sent outside your site unless you turn it on in Settings.',
							'sitewarden'
						) }
					</li>
					<li>
						{ __(
							'Runs in small steps in the background, so it is safe on shared hosting.',
							'sitewarden'
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
						{ __( 'Run first scan', 'sitewarden' ) }
					</button>
				</p>
			</div>
		</section>
	);
}

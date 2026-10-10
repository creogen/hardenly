<?php declare( strict_types=1 );

namespace Sitewarden\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Settings as SettingsApi;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Cron\Schedule;
use Sitewarden\Scanner\Group;
use Sitewarden\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The Settings screen: what to scan, schedule and email, and ignored findings.
 *
 * A plain Settings API form: it posts to options.php, which checks the nonce and the
 * `manage_options` capability, and saves through Settings::sanitize_form().
 */
class SettingsPage {
	public const SLUG  = 'sitewarden-settings';
	public const GROUP = 'sitewarden';

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Schedule
	 */
	private $schedule;

	/**
	 * @var SettingsApi
	 */
	private $settings_api;

	/**
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var Nonces
	 */
	private $nonces;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var Dates
	 */
	private $dates;

	public function __construct( Settings $settings, Schedule $schedule ) {
		$this->settings     = $settings;
		$this->schedule     = $schedule;
		$this->settings_api = ServiceFactory::create_settings();
		$this->capabilities = ServiceFactory::create_capabilities();
		$this->nonces       = ServiceFactory::create_nonces();
		$this->options      = ServiceFactory::create_options();
		$this->dates        = ServiceFactory::create_dates();
	}

	/**
	 * Registers the option with the Settings API. Runs on `admin_init`.
	 */
	public function register(): void {
		$this->settings_api->register_setting(
			self::GROUP,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->settings, 'sanitize_form' ],
				'default'           => Settings::DEFAULTS,
				'show_in_rest'      => false,
			]
		);
	}

	public function render(): void {
		if ( ! $this->capabilities->current_user_can( Menu::CAPABILITY ) ) {
			return;
		}
		$settings = $this->settings->get();
		$name     = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sitewarden Settings', 'sitewarden' ); ?></h1>
			<?php $this->settings_api->settings_errors(); ?>
			<form method="post" action="options.php">
				<input type="hidden" name="option_page" value="<?php echo esc_attr( self::GROUP ); ?>">
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[form]" value="1">
				<?php $this->nonces->wp_nonce_field( self::GROUP . '-options' ); ?>

				<h2 class="title"><?php esc_html_e( 'What to scan', 'sitewarden' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Checks', 'sitewarden' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Checks', 'sitewarden' ); ?></legend>
								<?php foreach ( $this->groups() as $group => $label ) : ?>
									<input type="hidden" name="<?php echo esc_attr( $name ); ?>[all_groups][]" value="<?php echo esc_attr( $group ); ?>">
									<p>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $name . '[groups][' . $group . ']' ); ?>" value="1" <?php echo in_array( $group, $settings['disabled_groups'], true ) ? '' : 'checked'; ?>>
											<strong><?php echo esc_html( $label[0] ); ?></strong>
										</label>
										<br><span class="description"><?php echo esc_html( $label[1] ); ?></span>
									</p>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sitewarden-modified-days"><?php esc_html_e( 'Recently changed files', 'sitewarden' ); ?></label></th>
						<td>
							<?php
							printf(
								/* translators: %s: number input for days. */
								esc_html__( 'Report PHP files changed in the last %s days', 'sitewarden' ),
								'<input type="number" id="sitewarden-modified-days" class="small-text" min="1" max="365" name="' . esc_attr( $name ) . '[modified_days]" value="' . esc_attr( (string) $settings['modified_days'] ) . '">'
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sitewarden-skip-files"><?php esc_html_e( 'Files to skip', 'sitewarden' ); ?></label></th>
						<td>
							<textarea id="sitewarden-skip-files" class="large-text code" rows="5" name="<?php echo esc_attr( $name ); ?>[skip_files]"><?php echo esc_textarea( implode( "\n", $settings['skip_files'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One file or folder per line, relative to the WordPress folder. File checks never report them. "Ignore this file" on the dashboard adds lines here.', 'sitewarden' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Schedule and email', 'sitewarden' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sitewarden-schedule"><?php esc_html_e( 'Automatic scan', 'sitewarden' ); ?></label></th>
						<td>
							<select id="sitewarden-schedule" name="<?php echo esc_attr( $name ); ?>[schedule]">
								<?php foreach ( $this->schedules() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php echo $value === $settings['schedule'] ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html( $this->next_run_text() ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email report', 'sitewarden' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Email report', 'sitewarden' ); ?></legend>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[email_enabled]" value="1" <?php echo $settings['email_enabled'] ? 'checked' : ''; ?>>
										<?php esc_html_e( 'Email a summary after automatic scans', 'sitewarden' ); ?>
									</label>
								</p>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[email_only_changes]" value="1" <?php echo $settings['email_only_changes'] ? 'checked' : ''; ?>>
										<?php esc_html_e( 'Only when the score drops or a new high or critical problem appears', 'sitewarden' ); ?>
									</label>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sitewarden-email-to"><?php esc_html_e( 'Send to', 'sitewarden' ); ?></label></th>
						<td>
							<input type="email" id="sitewarden-email-to" class="regular-text" name="<?php echo esc_attr( $name ); ?>[email_to]" value="<?php echo esc_attr( (string) $settings['email_to'] ); ?>" placeholder="<?php echo esc_attr( (string) $this->options->get_option( 'admin_email', '' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave empty to use the site admin email. The email lists scores and problems, never file paths.', 'sitewarden' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Ignored findings', 'sitewarden' ); ?></h2>
				<?php if ( [] === $settings['ignored'] ) : ?>
					<p><?php esc_html_e( 'No findings are ignored. Use "Ignore this finding" on the dashboard to hide a finding you have reviewed.', 'sitewarden' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Finding', 'sitewarden' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Ignored', 'sitewarden' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Show again', 'sitewarden' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $settings['ignored'] as $id => $entry ) : ?>
								<tr>
									<td><?php echo esc_html( '' !== $entry['message'] ? $entry['message'] : $id ); ?></td>
									<td>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: date, 2: username. */
												__( '%1$s by %2$s', 'sitewarden' ),
												(string) $this->dates->wp_date( (string) $this->options->get_option( 'date_format', 'Y-m-d' ), (int) $entry['at'] ),
												$entry['by']
											)
										);
										?>
									</td>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $name . '[restore][' . $id . ']' ); ?>" value="1">
											<span class="screen-reader-text">
												<?php
												/* translators: %s: finding. */
												echo esc_html( sprintf( __( 'Show "%s" again', 'sitewarden' ), $entry['message'] ) );
												?>
											</span>
										</label>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'sitewarden' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Group names and descriptions, in run order.
	 *
	 * @return array<string, array{string, string}>
	 */
	private function groups(): array {
		$labels       = Group::labels();
		$descriptions = [
			Group::CONFIG   => __( 'Debug output, file editing, HTTPS for the dashboard, security keys.', 'sitewarden' ),
			Group::USERS    => __( 'An "admin" username, number of administrators, inactive administrators, username listing.', 'sitewarden' ),
			Group::VERSIONS => __( 'Outdated WordPress, plugins, themes and PHP; unused plugins and themes.', 'sitewarden' ),
			Group::SERVER   => __( 'HTTPS, certificate expiry, security headers, folder listing, XML-RPC. Requests go only to your own site.', 'sitewarden' ),
			Group::DATABASE => __( 'Table prefix and size of autoloaded options.', 'sitewarden' ),
			Group::FILES    => __( 'PHP in uploads, file permissions, backups, debug log, .git and .env, recent changes.', 'sitewarden' ),
			Group::CODE     => __( 'Hidden or injected code in PHP files. The slowest check; findings need review.', 'sitewarden' ),
		];

		$groups = [];
		foreach ( $descriptions as $group => $description ) {
			$groups[ $group ] = [ $labels[ $group ], $description ];
		}
		return $groups;
	}

	/**
	 * @return array<string, string>
	 */
	private function schedules(): array {
		return [
			'off'    => __( 'Off', 'sitewarden' ),
			'weekly' => __( 'Weekly', 'sitewarden' ),
			'daily'  => __( 'Daily', 'sitewarden' ),
		];
	}

	private function next_run_text(): string {
		$next = $this->schedule->next_run();
		if ( null === $next ) {
			return __( 'Scans run only when you start them.', 'sitewarden' );
		}
		$format = $this->options->get_option( 'date_format', 'Y-m-d' ) . ' ' . $this->options->get_option( 'time_format', 'H:i' );
		return sprintf(
			/* translators: %s: date and time. */
			__( 'Next scan: %s. WP-Cron starts it with the first visit after that time.', 'sitewarden' ),
			(string) $this->dates->wp_date( $format, $next )
		);
	}
}

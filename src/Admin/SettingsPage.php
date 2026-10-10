<?php declare( strict_types=1 );

namespace Sitewarden\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Redirects;
use Merkushin\Wpal\Service\Settings as SettingsApi;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Cron\Schedule;
use Sitewarden\Scanner\Group;
use Sitewarden\Storage\Settings;
use Sitewarden\Vulnerability\ProviderException;
use Sitewarden\Vulnerability\ProviderRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * The Settings screen: what to scan, schedule and email, online services and ignored findings.
 *
 * A plain Settings API form: it posts to options.php, which checks the nonce and the
 * `manage_options` capability, and saves through Settings::sanitize_form().
 */
class SettingsPage {
	public const SLUG  = 'sitewarden-settings';
	public const GROUP = 'sitewarden';

	/**
	 * admin-post.php action of the "Test connection" button.
	 */
	public const TEST_ACTION = 'sitewarden_test_provider';

	/**
	 * Holds the last connection test's outcome for a minute, to show after the redirect.
	 */
	public const TEST_TRANSIENT = 'sitewarden_provider_test';

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

	/**
	 * @var ProviderRegistry
	 */
	private $providers;

	/**
	 * @var Transient
	 */
	private $transients;

	/**
	 * @var Users
	 */
	private $users;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var Redirects
	 */
	private $redirects;

	public function __construct( Settings $settings, Schedule $schedule, ProviderRegistry $providers ) {
		$this->settings     = $settings;
		$this->schedule     = $schedule;
		$this->providers    = $providers;
		$this->transients   = ServiceFactory::create_transient();
		$this->users        = ServiceFactory::create_users();
		$this->urls         = ServiceFactory::create_urls();
		$this->redirects    = ServiceFactory::create_redirects();
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
			<?php $this->render_test_notice(); ?>
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
							<p class="description"><?php esc_html_e( 'One file or folder per line, relative to the WordPress folder. File checks never report them. "Skip this file" on the dashboard adds lines here.', 'sitewarden' ); ?></p>
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

				<?php $this->render_online_services( $settings ); ?>

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

			<?php if ( '' !== $settings['vuln_provider'] && '' !== $settings['vuln_api_key'] ) : ?>
				<form method="post" action="<?php echo esc_url( (string) $this->urls->admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
					<?php $this->nonces->wp_nonce_field( self::TEST_ACTION ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Test the vulnerability lookup connection', 'sitewarden' ); ?></button>
					<span class="description"><?php esc_html_e( 'Uses the saved key and makes one request to the provider.', 'sitewarden' ); ?></span>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The "Online services" section: everything here is off by default and sends data out.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_online_services( array $settings ): void {
		$name     = Settings::OPTION;
		$key      = (string) $settings['vuln_api_key'];
		$selected = (string) $settings['vuln_provider'];
		?>
		<h2 class="title"><?php esc_html_e( 'Online services', 'sitewarden' ); ?></h2>
		<p><?php esc_html_e( 'Off by default. Turning one on sends data from your site to a third party, as described under each option. Nothing else ever leaves your site.', 'sitewarden' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'File integrity', 'sitewarden' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[checksums_enabled]" value="1" <?php echo $settings['checksums_enabled'] ? 'checked' : ''; ?>>
						<?php esc_html_e( 'Compare WordPress core and plugin files with WordPress.org', 'sitewarden' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Sends your WordPress version and language, and the slugs and versions of installed plugins, to api.wordpress.org and downloads.wordpress.org to get the official file checksums.', 'sitewarden' ); ?>
						<a href="https://wordpress.org/about/privacy/"><?php esc_html_e( 'WordPress.org privacy policy', 'sitewarden' ); ?></a>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Vulnerability lookup', 'sitewarden' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Vulnerability lookup provider', 'sitewarden' ); ?></legend>
						<p>
							<label>
								<input type="radio" name="<?php echo esc_attr( $name ); ?>[vuln_provider]" value="" <?php echo '' === $selected ? 'checked' : ''; ?>>
								<?php esc_html_e( 'None', 'sitewarden' ); ?>
							</label>
						</p>
						<?php foreach ( $this->providers->all() as $id => $provider ) : ?>
							<p>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[vuln_provider]" value="<?php echo esc_attr( $id ); ?>" <?php echo $id === $selected ? 'checked' : ''; ?>>
									<?php echo esc_html( $provider->name() ); ?>
								</label>
								<br><span class="description">
									<?php echo esc_html( $provider->plan_note() ); ?>
									<a href="<?php echo esc_url( $provider->signup_url() ); ?>"><?php esc_html_e( 'Get a key', 'sitewarden' ); ?></a>
									· <a href="<?php echo esc_url( $provider->terms_url() ); ?>"><?php esc_html_e( 'Terms', 'sitewarden' ); ?></a>
								</span>
							</p>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sitewarden-api-key"><?php esc_html_e( 'API key', 'sitewarden' ); ?></label></th>
				<td>
					<input type="password" id="sitewarden-api-key" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[vuln_api_key]" value="">
					<?php if ( '' !== $key ) : ?>
						<p class="description">
							<?php
							/* translators: %s: last four characters of the saved key. */
							echo esc_html( sprintf( __( 'A key ending in %s is saved. Leave the field empty to keep it.', 'sitewarden' ), substr( $key, -4 ) ) );
							?>
						</p>
						<p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[vuln_remove_key]" value="1">
								<?php esc_html_e( 'Remove the saved key', 'sitewarden' ); ?>
							</label>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Your own key from the provider you chose.', 'sitewarden' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Consent', 'sitewarden' ); ?></th>
				<td>
					<?php
					$provider = $this->providers->get( $selected );
					if ( null === $provider ) :
						?>
						<p class="description"><?php esc_html_e( 'Choose a provider and save to see what it receives.', 'sitewarden' ); ?></p>
					<?php else : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[vuln_consent]" value="1" <?php echo $settings['vuln_consent'] ? 'checked' : ''; ?>>
							<?php echo esc_html( $provider->data_sent() ); ?>
						</label>
						<?php if ( ! $settings['vuln_consent'] ) : ?>
							<p class="description"><?php esc_html_e( 'Lookups do not run until you agree.', 'sitewarden' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Handles "Test connection" (admin-post.php): checks the saved key with one request.
	 */
	public function handle_test(): void {
		$this->redirects->wp_safe_redirect( $this->test(), 303 );
		exit;
	}

	/**
	 * Runs the connection test and returns where to go next.
	 */
	public function test(): string {
		$back = (string) $this->urls->admin_url( 'admin.php?page=' . self::SLUG );
		if ( ! $this->capabilities->current_user_can( Menu::CAPABILITY ) ) {
			return $back;
		}
		$this->nonces->check_admin_referer( self::TEST_ACTION );

		$settings = $this->settings->get();
		$provider = $this->providers->get( (string) $settings['vuln_provider'] );
		if ( null === $provider || '' === $settings['vuln_api_key'] ) {
			$outcome = [ false, __( 'Choose a provider and save a key first.', 'sitewarden' ) ];
		} elseif ( true !== $settings['vuln_consent'] ) {
			$outcome = [ false, __( 'Agree to send data to the provider and save before testing.', 'sitewarden' ) ];
		} else {
			try {
				$outcome = [ true, $provider->test( (string) $settings['vuln_api_key'] ) ];
			} catch ( ProviderException $e ) {
				$outcome = [ false, $e->getMessage() ];
			}
		}

		$this->transients->set_transient(
			self::TEST_TRANSIENT,
			[
				'user'    => (int) $this->users->get_current_user_id(),
				'ok'      => $outcome[0],
				'message' => $outcome[1],
			],
			MINUTE_IN_SECONDS
		);
		return $back;
	}

	private function render_test_notice(): void {
		$test = $this->transients->get_transient( self::TEST_TRANSIENT );
		if ( ! is_array( $test ) || ( $test['user'] ?? 0 ) !== (int) $this->users->get_current_user_id() ) {
			return;
		}
		$this->transients->delete_transient( self::TEST_TRANSIENT );
		?>
		<div class="notice <?php echo ! empty( $test['ok'] ) ? 'notice-success' : 'notice-error'; ?>">
			<p><?php echo esc_html( (string) ( $test['message'] ?? '' ) ); ?></p>
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
			Group::ONLINE   => __( 'File integrity and vulnerability lookups. Run only when turned on under Online services below.', 'sitewarden' ),
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

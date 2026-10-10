<?php declare( strict_types=1 );

namespace Hardenly\Storage;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings, all in one option.
 *
 * Every write goes through sanitize(), which accepts both the stored shape and the settings
 * form's fields, so the option is valid however it is written.
 */
class Settings {
	public const OPTION = 'hardenly_settings';

	public const SCHEDULES = [ 'off', 'daily', 'weekly' ];

	/**
	 * At most this many entries in "Files to skip" and in the ignored findings.
	 */
	public const MAX_ENTRIES = 500;

	public const DEFAULTS = [
		// Check groups turned off.
		'disabled_groups'    => [],
		// "Recently changed files" reports PHP files changed in this many days.
		'modified_days'      => 7,
		// Paths, relative to ABSPATH, that file checks never report.
		'skip_files'         => [],
		// Hidden findings: id => [ 'message' => ..., 'at' => timestamp, 'by' => user login ].
		'ignored'            => [],
		// Automatic scans: off, daily or weekly.
		'schedule'           => 'off',
		// Email a summary after automatic scans.
		'email_enabled'      => false,
		// Only when the score drops or a new high or critical problem appears.
		'email_only_changes' => true,
		// Recipient; empty means the site admin email.
		'email_to'           => '',
		// Online services, all off by default.
		// Compare core and plugin files with WordPress.org checksums.
		'checksums_enabled'  => false,
		// Vulnerability lookup provider id ('' for none), its API key, and the user's consent to
		// send plugin and theme slugs and versions to it.
		'vuln_provider'      => '',
		'vuln_api_key'       => '',
		'vuln_consent'       => false,
	];

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var Sanitization
	 */
	private $sanitization;

	public function __construct() {
		$this->options      = ServiceFactory::create_options();
		$this->sanitization = ServiceFactory::create_sanitization();
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = $this->options->get_option( self::OPTION, [] );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Changes some settings and saves them.
	 *
	 * @param array<string, mixed> $changes Settings in the stored shape.
	 *
	 * @return array<string, mixed> The saved settings.
	 */
	public function update( array $changes ): array {
		$settings = $this->sanitize( array_merge( $this->get(), $changes ) );
		$this->options->update_option( self::OPTION, $settings, false );
		return $settings;
	}

	/**
	 * Hides a finding from the score, counts and email.
	 *
	 * @param string $id      Finding id (check id, plus ":key" for checks with several results).
	 * @param string $message Its message, to show in the list of ignored findings.
	 * @param string $by      Login of the user who ignored it.
	 *
	 * @return array<string, mixed> The saved settings.
	 */
	public function ignore( string $id, string $message, string $by ): array {
		$ignored        = $this->get()['ignored'];
		$ignored[ $id ] = [
			'message' => $message,
			'at'      => time(),
			'by'      => $by,
		];
		return $this->update( [ 'ignored' => $ignored ] );
	}

	/**
	 * Shows an ignored finding again.
	 *
	 * @return array<string, mixed> The saved settings.
	 */
	public function restore( string $id ): array {
		$ignored = $this->get()['ignored'];
		unset( $ignored[ $id ] );
		return $this->update( [ 'ignored' => $ignored ] );
	}

	/**
	 * Adds a path to "Files to skip".
	 *
	 * @return array<string, mixed> The saved settings.
	 */
	public function skip_file( string $path ): array {
		$skip   = $this->get()['skip_files'];
		$skip[] = $path;
		return $this->update( [ 'skip_files' => $skip ] );
	}

	/**
	 * IDs of the ignored findings.
	 *
	 * @return string[]
	 */
	public function ignored_ids(): array {
		return array_map( 'strval', array_keys( $this->get()['ignored'] ) );
	}

	/**
	 * Sanitizes the settings form submission (register_setting's sanitize_callback).
	 *
	 * The form posts `form` with checkboxes per group and text fields; anything else (such as
	 * a write from update()) is already in the stored shape. Keys the form does not post, such
	 * as the ignored findings, keep their stored values.
	 *
	 * @param mixed $input Posted or stored value.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_form( $input ): array {
		if ( ! is_array( $input ) ) {
			return $this->get();
		}
		if ( empty( $input['form'] ) ) {
			return $this->sanitize( array_merge( $this->get(), $input ) );
		}

		$settings = $this->get();

		$enabled                        = isset( $input['groups'] ) && is_array( $input['groups'] ) ? array_keys( $input['groups'] ) : [];
		$settings['disabled_groups']    = array_values( array_diff( (array) ( $input['all_groups'] ?? [] ), $enabled ) );
		$settings['modified_days']      = $input['modified_days'] ?? $settings['modified_days'];
		$settings['skip_files']         = preg_split( '/\R/', (string) ( $input['skip_files'] ?? '' ) );
		$settings['schedule']           = $input['schedule'] ?? $settings['schedule'];
		$settings['email_enabled']      = ! empty( $input['email_enabled'] );
		$settings['email_only_changes'] = ! empty( $input['email_only_changes'] );
		$settings['email_to']           = $input['email_to'] ?? '';
		$settings['checksums_enabled']  = ! empty( $input['checksums_enabled'] );

		// The key belongs to a provider: switching providers drops it. An empty field keeps
		// the saved key, so it never has to be shown in the form.
		$provider = (string) ( $input['vuln_provider'] ?? '' );
		$key      = trim( (string) ( $input['vuln_api_key'] ?? '' ) );
		if ( $provider !== $settings['vuln_provider'] || ! empty( $input['vuln_remove_key'] ) ) {
			$settings['vuln_api_key'] = '';
		}
		if ( '' !== $key ) {
			$settings['vuln_api_key'] = $key;
		}
		$settings['vuln_provider'] = $provider;
		$settings['vuln_consent']  = ! empty( $input['vuln_consent'] );

		$restore = isset( $input['restore'] ) && is_array( $input['restore'] ) ? array_keys( $input['restore'] ) : [];
		foreach ( $restore as $id ) {
			unset( $settings['ignored'][ $id ] );
		}

		return $this->sanitize( $settings );
	}

	/**
	 * Settings in the stored shape, with every value valid.
	 *
	 * @param array<string, mixed> $settings Settings to clean.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( array $settings ): array {
		$clean = self::DEFAULTS;

		$clean['disabled_groups'] = array_values(
			array_unique(
				array_filter(
					array_map( [ $this->sanitization, 'sanitize_key' ], self::strings( $settings['disabled_groups'] ?? [] ) )
				)
			)
		);

		$clean['modified_days'] = min( 365, max( 1, (int) ( $settings['modified_days'] ?? self::DEFAULTS['modified_days'] ) ) );

		$clean['skip_files'] = array_slice(
			array_values( array_unique( array_filter( array_map( [ self::class, 'clean_path' ], self::strings( $settings['skip_files'] ?? [] ) ) ) ) ),
			0,
			self::MAX_ENTRIES
		);

		$ignored = isset( $settings['ignored'] ) && is_array( $settings['ignored'] ) ? $settings['ignored'] : [];
		foreach ( array_slice( $ignored, 0, self::MAX_ENTRIES, true ) as $id => $entry ) {
			$id = self::clean_id( (string) $id );
			if ( '' === $id || ! is_array( $entry ) ) {
				continue;
			}
			$clean['ignored'][ $id ] = [
				'message' => (string) $this->sanitization->sanitize_text_field( (string) ( $entry['message'] ?? '' ) ),
				'at'      => (int) ( $entry['at'] ?? 0 ),
				'by'      => (string) $this->sanitization->sanitize_user( (string) ( $entry['by'] ?? '' ) ),
			];
		}

		$schedule          = (string) ( $settings['schedule'] ?? 'off' );
		$clean['schedule'] = in_array( $schedule, self::SCHEDULES, true ) ? $schedule : 'off';

		$clean['email_enabled']      = (bool) ( $settings['email_enabled'] ?? false );
		$clean['email_only_changes'] = (bool) ( $settings['email_only_changes'] ?? true );

		$email             = (string) $this->sanitization->sanitize_email( (string) ( $settings['email_to'] ?? '' ) );
		$clean['email_to'] = '' !== $email && $this->sanitization->is_email( $email ) ? $email : '';

		$clean['checksums_enabled'] = (bool) ( $settings['checksums_enabled'] ?? false );
		$clean['vuln_provider']     = (string) $this->sanitization->sanitize_key( (string) ( $settings['vuln_provider'] ?? '' ) );
		$clean['vuln_api_key']      = substr( (string) preg_replace( '/[^\x21-\x7E]/', '', (string) ( $settings['vuln_api_key'] ?? '' ) ), 0, 200 );
		$clean['vuln_consent']      = '' !== $clean['vuln_provider'] && (bool) ( $settings['vuln_consent'] ?? false );

		return $clean;
	}

	/**
	 * Whether vulnerability lookups may run: a provider, its key and the user's consent.
	 */
	public function vulnerability_lookup_ready(): bool {
		$settings = $this->get();
		return '' !== $settings['vuln_provider'] && '' !== $settings['vuln_api_key'] && true === $settings['vuln_consent'];
	}

	/**
	 * A finding id: a check id, optionally with ":key".
	 */
	public static function clean_id( string $id ): string {
		return 1 === preg_match( '/^[a-z0-9_.-]{1,100}(?::[A-Za-z0-9_.\/-]{1,200})?$/', $id ) ? $id : '';
	}

	/**
	 * A path relative to the WordPress folder, or '' when it is not one.
	 */
	public static function clean_path( string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );
		$path = trim( (string) preg_replace( '~/+~', '/', $path ), '/' );
		if ( '' === $path || strlen( $path ) > 500 || preg_match( '~(^|/)\.\.(/|$)|[\x00-\x1F<>"|?*]~', $path ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * @param mixed $value List of strings, or anything else.
	 *
	 * @return string[]
	 */
	private static function strings( $value ): array {
		return is_array( $value ) ? array_map( 'strval', array_filter( $value, 'is_scalar' ) ) : [];
	}
}

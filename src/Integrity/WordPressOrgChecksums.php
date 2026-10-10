<?php declare( strict_types=1 );

namespace Sitewarden\Integrity;

use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Http;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\Service\Updates;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Site\AdminIncludes;

defined( 'ABSPATH' ) || exit;

/**
 * Official file checksums from WordPress.org. Used only when "Compare core and plugin files
 * with WordPress.org" is on; sends the WordPress version and locale, or a plugin's slug and
 * version, and nothing else.
 */
class WordPressOrgChecksums {
	public const CORE_TRANSIENT = 'sitewarden_core_checksums';

	private const PLUGIN_URL = 'https://downloads.wordpress.org/plugin-checksums/%s/%s.json';

	/**
	 * @var Updates
	 */
	private $updates;

	/**
	 * @var Http
	 */
	private $http;

	/**
	 * @var Errors
	 */
	private $errors;

	/**
	 * @var Transient
	 */
	private $transients;

	public function __construct() {
		$this->updates    = ServiceFactory::create_updates();
		$this->http       = ServiceFactory::create_http();
		$this->errors     = ServiceFactory::create_errors();
		$this->transients = ServiceFactory::create_transient();
	}

	/**
	 * Core checksums, cached for a day.
	 *
	 * @return array<string, string[]>|null Allowed MD5 hashes by path, or null when unavailable.
	 */
	public function core( string $version, string $locale ): ?array {
		$cached = $this->transients->get_transient( self::CORE_TRANSIENT );
		if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $version . '/' . $locale && is_array( $cached['files'] ?? null ) ) {
			return $cached['files'];
		}

		AdminIncludes::updates();
		$checksums = $this->updates->get_core_checksums( $version, $locale );
		if ( ! is_array( $checksums ) && 'en_US' !== $locale ) {
			// Not every locale has its own package; the English one has the same PHP files.
			$checksums = $this->updates->get_core_checksums( $version, 'en_US' );
		}
		if ( ! is_array( $checksums ) || [] === $checksums ) {
			return null;
		}

		$files = [];
		foreach ( $checksums as $path => $md5 ) {
			$files[ (string) $path ] = array_map( 'strval', (array) $md5 );
		}
		$this->transients->set_transient(
			self::CORE_TRANSIENT,
			[
				'key'   => $version . '/' . $locale,
				'files' => $files,
			],
			DAY_IN_SECONDS
		);
		return $files;
	}

	/**
	 * Checksums of a plugin version hosted on WordPress.org.
	 *
	 * @return array<string, string[]>|null Allowed MD5 hashes by path in the plugin folder, or null
	 *                                      when WordPress.org does not have this plugin version.
	 */
	public function plugin( string $slug, string $version ): ?array {
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) || 1 !== preg_match( '/^[A-Za-z0-9.+_-]+$/', $version ) ) {
			return null;
		}

		$response = $this->http->wp_safe_remote_get(
			sprintf( self::PLUGIN_URL, rawurlencode( $slug ), rawurlencode( $version ) ),
			[ 'timeout' => 15 ]
		);
		if ( $this->errors->is_wp_error( $response ) || 200 !== (int) $this->http->wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) $this->http->wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['files'] ) || ! is_array( $data['files'] ) ) {
			return null;
		}

		$files = [];
		foreach ( $data['files'] as $path => $hashes ) {
			if ( is_array( $hashes ) && isset( $hashes['md5'] ) ) {
				$files[ (string) $path ] = array_map( 'strval', (array) $hashes['md5'] );
			}
		}
		return $files;
	}
}

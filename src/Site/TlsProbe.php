<?php declare( strict_types=1 );

namespace Sitewarden\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the expiry date of the site's own TLS certificate.
 *
 * The WordPress HTTP API does not expose certificates, so this opens a TLS connection with a
 * stream context. It connects only to the site's own host.
 */
class TlsProbe {
	private const TIMEOUT = 10;

	/**
	 * @param string $host Host name of this site.
	 * @param int    $port HTTPS port.
	 *
	 * @return int|null Expiry as a Unix timestamp, or null when the certificate cannot be read.
	 */
	public function expires_at( string $host, int $port = 443 ): ?int {
		if ( ! function_exists( 'openssl_x509_parse' ) || '' === $host ) {
			return null;
		}

		$context = stream_context_create(
			[
				'ssl' => [
					'capture_peer_cert' => true,
					// Only the date is read; validity is not the question here.
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'SNI_enabled'       => true,
					'peer_name'         => $host,
				],
			]
		);

		$errno  = 0;
		$errstr = '';
		// The HTTP API does not expose the peer certificate. A failed connection only means the date is unknown,
		// so its warning is silenced and the failure handled below.
		// phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged
		$socket = @stream_socket_client( 'ssl://' . $host . ':' . $port, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context );
		if ( false === $socket ) {
			return null;
		}

		$params = stream_context_get_params( $socket );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the socket opened above.
		fclose( $socket );

		$certificate = $params['options']['ssl']['peer_certificate'] ?? null;
		if ( null === $certificate ) {
			return null;
		}
		$parsed = openssl_x509_parse( $certificate );
		return is_array( $parsed ) && isset( $parsed['validTo_time_t'] ) ? (int) $parsed['validTo_time_t'] : null;
	}
}

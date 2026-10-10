<?php declare( strict_types=1 );

namespace Hardenly\Site;

use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Http;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP requests from the site to itself, to see what visitors see (headers, exposed files).
 *
 * Only for URLs of this site; nothing is sent elsewhere. Like Site Health's loopback test,
 * certificates are checked as the `https_local_ssl_verify` filter says.
 */
class Loopback {
	private const TIMEOUT = 10;

	private const MAX_BODY_BYTES = 512 * 1024;

	/**
	 * @var Http
	 */
	private $http;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Errors
	 */
	private $errors;

	public function __construct() {
		$this->http   = ServiceFactory::create_http();
		$this->hooks  = ServiceFactory::create_hooks();
		$this->errors = ServiceFactory::create_errors();
	}

	/**
	 * @param string               $url  URL on this site.
	 * @param array<string, mixed> $args Extra request arguments.
	 *
	 * @return array{code: int, headers: array<string, string>, body: string}|null Null when the request failed.
	 */
	public function get( string $url, array $args = [] ): ?array {
		return $this->response( $this->http->wp_remote_get( $url, $this->args( $args ) ) );
	}

	/**
	 * @param string               $url  URL on this site.
	 * @param array<string, mixed> $args Extra request arguments, e.g. `body`.
	 *
	 * @return array{code: int, headers: array<string, string>, body: string}|null Null when the request failed.
	 */
	public function post( string $url, array $args = [] ): ?array {
		return $this->response( $this->http->wp_remote_post( $url, $this->args( $args ) ) );
	}

	/**
	 * @param array<string, mixed> $args Extra request arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function args( array $args ): array {
		return array_merge(
			[
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BODY_BYTES,
				'sslverify'           => (bool) $this->hooks->apply_filters( 'https_local_ssl_verify', false ),
			],
			$args
		);
	}

	/**
	 * @param mixed $response Response from the HTTP API.
	 *
	 * @return array{code: int, headers: array<string, string>, body: string}|null
	 */
	private function response( $response ): ?array {
		if ( $this->errors->is_wp_error( $response ) || ! is_array( $response ) ) {
			return null;
		}

		$headers = [];
		foreach ( $this->http->wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return [
			'code'    => (int) $this->http->wp_remote_retrieve_response_code( $response ),
			'headers' => $headers,
			'body'    => (string) $this->http->wp_remote_retrieve_body( $response ),
		];
	}
}

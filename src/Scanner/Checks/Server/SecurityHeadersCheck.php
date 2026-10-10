<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * Security headers on the home page.
 */
class SecurityHeadersCheck implements Check {
	public const ID = 'server.headers';

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Urls
	 */
	private $urls;

	public function __construct( ?Loopback $loopback = null ) {
		$this->loopback = $loopback ?? new Loopback();
		$this->urls     = ServiceFactory::create_urls();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Security headers', 'hardenly' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$url = (string) $this->urls->home_url( '/' );
		// Follows redirects (to https:// or www.), so the headers are those of the page visitors get.
		$response = $this->loopback->get( $url, [ 'redirection' => 3 ] );
		if ( null === $response || 200 !== $response['code'] ) {
			return [ Result::skipped( self::ID, __( 'The home page could not be loaded to read its headers.', 'hardenly' ) ) ];
		}

		$headers = $response['headers'];
		$missing = [];
		if ( ! isset( $headers['x-frame-options'] ) && false === stripos( $headers['content-security-policy'] ?? '', 'frame-ancestors' ) ) {
			$missing[] = __( 'X-Frame-Options or Content-Security-Policy frame-ancestors (stops other sites framing yours)', 'hardenly' );
		}
		if ( 'nosniff' !== strtolower( trim( $headers['x-content-type-options'] ?? '' ) ) ) {
			$missing[] = __( 'X-Content-Type-Options: nosniff (stops browsers guessing file types)', 'hardenly' );
		}
		if ( ! isset( $headers['referrer-policy'] ) ) {
			$missing[] = __( 'Referrer-Policy (limits what other sites learn about your URLs)', 'hardenly' );
		}
		if ( 0 === strpos( $url, 'https://' ) && ! isset( $headers['strict-transport-security'] ) ) {
			$missing[] = __( 'Strict-Transport-Security (keeps browsers on HTTPS)', 'hardenly' );
		}

		if ( [] === $missing ) {
			return [ Result::pass( self::ID, __( 'The home page sends the main security headers.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				/* translators: %d: number of headers. */
				sprintf( _n( '%d security header is missing', '%d security headers are missing', count( $missing ), 'hardenly' ), count( $missing ) ),
				__( 'Add the headers in your server configuration or with a security plugin. Your host can help. Test the site afterwards, especially embeds.', 'hardenly' ),
				$missing,
				__( 'These headers tell browsers to block common attacks such as clickjacking.', 'hardenly' )
			),
		];
	}
}

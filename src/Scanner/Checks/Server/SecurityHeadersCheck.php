<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Loopback;

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
		return __( 'Security headers', 'sitewarden' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$url = (string) $this->urls->home_url( '/' );
		// Follows redirects (to https:// or www.), so the headers are those of the page visitors get.
		$response = $this->loopback->get( $url, [ 'redirection' => 3 ] );
		if ( null === $response || 200 !== $response['code'] ) {
			return [ Result::skipped( self::ID, __( 'The home page could not be loaded to read its headers.', 'sitewarden' ) ) ];
		}

		$headers = $response['headers'];
		$missing = [];
		if ( ! isset( $headers['x-frame-options'] ) && false === stripos( $headers['content-security-policy'] ?? '', 'frame-ancestors' ) ) {
			$missing[] = __( 'X-Frame-Options or Content-Security-Policy frame-ancestors (stops other sites framing yours)', 'sitewarden' );
		}
		if ( 'nosniff' !== strtolower( trim( $headers['x-content-type-options'] ?? '' ) ) ) {
			$missing[] = __( 'X-Content-Type-Options: nosniff (stops browsers guessing file types)', 'sitewarden' );
		}
		if ( ! isset( $headers['referrer-policy'] ) ) {
			$missing[] = __( 'Referrer-Policy (limits what other sites learn about your URLs)', 'sitewarden' );
		}
		if ( 0 === strpos( $url, 'https://' ) && ! isset( $headers['strict-transport-security'] ) ) {
			$missing[] = __( 'Strict-Transport-Security (keeps browsers on HTTPS)', 'sitewarden' );
		}

		if ( [] === $missing ) {
			return [ Result::pass( self::ID, __( 'The home page sends the main security headers.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				/* translators: %d: number of headers. */
				sprintf( _n( '%d security header is missing', '%d security headers are missing', count( $missing ), 'sitewarden' ), count( $missing ) ),
				__( 'Add the headers in your server configuration or with a security plugin. Your host can help. Test the site afterwards, especially embeds.', 'sitewarden' ),
				$missing,
				__( 'These headers tell browsers to block common attacks such as clickjacking.', 'sitewarden' )
			),
		];
	}
}

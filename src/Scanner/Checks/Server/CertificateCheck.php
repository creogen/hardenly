<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\TlsProbe;

defined( 'ABSPATH' ) || exit;

/**
 * Expiry of the site's TLS certificate.
 */
class CertificateCheck implements Check {
	public const ID = 'server.certificate';

	/**
	 * @var TlsProbe
	 */
	private $probe;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @param TlsProbe|null $probe Reads the certificate.
	 * @param callable|null $clock Returns the time; time() by default.
	 */
	public function __construct( ?TlsProbe $probe = null, ?callable $clock = null ) {
		$this->probe = $probe ?? new TlsProbe();
		$this->urls  = ServiceFactory::create_urls();
		$this->clock = $clock ?? 'time';
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Certificate expiry', 'sitewarden' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$url = (string) $this->urls->home_url();
		if ( 0 !== strpos( $url, 'https://' ) ) {
			return [ Result::skipped( self::ID, __( 'The site does not use HTTPS.', 'sitewarden' ) ) ];
		}

		$parts   = (array) $this->urls->wp_parse_url( $url );
		$expires = $this->probe->expires_at( (string) ( $parts['host'] ?? '' ), (int) ( $parts['port'] ?? 443 ) );
		if ( null === $expires ) {
			return [ Result::skipped( self::ID, __( 'The certificate could not be read from this server.', 'sitewarden' ) ) ];
		}

		$days = (int) floor( ( $expires - (int) ( $this->clock )() ) / DAY_IN_SECONDS );
		if ( $days >= 30 ) {
			/* translators: %d: number of days. */
			return [ Result::pass( self::ID, sprintf( __( 'The certificate is valid for %d more days.', 'sitewarden' ), $days ) ) ];
		}

		if ( $days < 0 ) {
			$severity = Result::SEVERITY_CRITICAL;
			$message  = __( 'The HTTPS certificate has expired', 'sitewarden' );
		} else {
			$severity = $days < 14 ? Result::SEVERITY_HIGH : Result::SEVERITY_MEDIUM;
			/* translators: %d: number of days. */
			$message = sprintf( _n( 'The HTTPS certificate expires in %d day', 'The HTTPS certificate expires in %d days', $days, 'sitewarden' ), $days );
		}

		return [
			Result::fail(
				self::ID,
				$severity,
				$message,
				__( 'Renew the certificate, or ask your host why automatic renewal did not run.', 'sitewarden' ),
				[],
				__( 'With an expired certificate, browsers warn visitors away and some connections fail.', 'sitewarden' )
			),
		];
	}
}

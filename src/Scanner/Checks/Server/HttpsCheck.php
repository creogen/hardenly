<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Site and WordPress addresses use HTTPS.
 */
class HttpsCheck implements Check {
	public const ID = 'server.https';

	/**
	 * @var Urls
	 */
	private $urls;

	public function __construct() {
		$this->urls = ServiceFactory::create_urls();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'HTTPS', 'hardenly' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$insecure = [];
		foreach ( [ (string) $this->urls->home_url(), (string) $this->urls->site_url() ] as $url ) {
			if ( 0 !== strpos( $url, 'https://' ) ) {
				$insecure[] = $url;
			}
		}

		if ( [] === $insecure ) {
			return [ Result::pass( self::ID, __( 'The site uses HTTPS.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_HIGH,
				__( 'The site does not use HTTPS', 'hardenly' ),
				__( 'Get a certificate from your host (most offer free ones), then change both addresses in Settings > General to https://. Tools > Site Health can help.', 'hardenly' ),
				array_values( array_unique( $insecure ) ),
				__( 'Without HTTPS, passwords and cookies travel unencrypted and can be read on public networks.', 'hardenly' )
			),
		];
	}
}

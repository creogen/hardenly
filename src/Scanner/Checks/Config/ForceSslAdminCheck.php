<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * HTTPS forced for logins and the dashboard on a site that supports it.
 */
class ForceSslAdminCheck implements Check {
	public const ID = 'config.force_ssl_admin';

	/**
	 * @var Constants
	 */
	private $constants;

	/**
	 * @var Urls
	 */
	private $urls;

	public function __construct( ?Constants $constants = null ) {
		$this->constants = $constants ?? new Constants();
		$this->urls      = ServiceFactory::create_urls();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'HTTPS for the dashboard', 'hardenly' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		if ( 0 !== strpos( (string) $this->urls->site_url(), 'https://' ) ) {
			// The HTTPS check reports a site without HTTPS.
			return [ Result::skipped( self::ID, __( 'The site does not use HTTPS.', 'hardenly' ) ) ];
		}
		if ( $this->constants->is_true( 'FORCE_SSL_ADMIN' ) ) {
			return [ Result::pass( self::ID, __( 'Logins and the dashboard always use HTTPS.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				__( 'HTTPS is not forced for logins and the dashboard', 'hardenly' ),
				__( "Add define( 'FORCE_SSL_ADMIN', true ); to wp-config.php.", 'hardenly' ),
				[],
				__( 'Without it, a login over plain HTTP sends the password unencrypted.', 'hardenly' )
			),
		];
	}
}

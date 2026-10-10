<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * The environment type (WP_ENVIRONMENT_TYPE). Some checks are milder outside production.
 */
class EnvironmentTypeCheck implements Check {
	public const ID = 'config.environment';

	/**
	 * @var Environment
	 */
	private $environment;

	public function __construct() {
		$this->environment = ServiceFactory::create_environment();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Environment type', 'hardenly' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		$type = (string) $this->environment->wp_get_environment_type();
		if ( 'production' === $type ) {
			return [ Result::pass( self::ID, __( 'The site is marked as a production site.', 'hardenly' ) ) ];
		}

		return [
			Result::info(
				self::ID,
				/* translators: %s: environment type, e.g. "staging". */
				sprintf( __( 'The site is marked as a %s site', 'hardenly' ), $type ),
				__( 'WP_ENVIRONMENT_TYPE in wp-config.php says this is not a live site, so debug output is rated lower. If this is your live site, remove WP_ENVIRONMENT_TYPE or set it to "production".', 'hardenly' )
			),
		];
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Config;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Security keys and salts in wp-config.php: set, unique and not the sample placeholder.
 */
class SaltsCheck implements Check {
	public const ID = 'config.salts';

	public const KEYS = [
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	];

	/**
	 * Shorter keys are treated as weak.
	 */
	private const MIN_LENGTH = 32;

	/**
	 * @var Constants
	 */
	private $constants;

	public function __construct( ?Constants $constants = null ) {
		$this->constants = $constants ?? new Constants();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Security keys', 'sitewarden' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		$weak   = [];
		$values = [];
		foreach ( self::KEYS as $name ) {
			$value = (string) $this->constants->get( $name, '' );
			if ( strlen( $value ) < self::MIN_LENGTH || false !== stripos( $value, 'put your unique phrase here' ) || isset( $values[ $value ] ) ) {
				$weak[] = $name;
			}
			$values[ $value ] = true;
		}

		if ( [] === $weak ) {
			return [ Result::pass( self::ID, __( 'Security keys are set and unique.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_HIGH,
				__( 'Security keys are missing, weak or repeated', 'sitewarden' ),
				__( 'Replace the keys in wp-config.php with new ones from https://api.wordpress.org/secret-key/1.1/salt/. Everyone will be logged out once.', 'sitewarden' ),
				$weak,
				__( 'The keys protect login cookies. With known or empty keys, cookies are easier to forge.', 'sitewarden' )
			),
		];
	}
}

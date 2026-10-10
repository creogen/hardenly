<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Database;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Size of the options WordPress loads on every request.
 */
class AutoloadSizeCheck implements Check {
	public const ID = 'database.autoload_size';

	/**
	 * Same limit as Site Health's "Autoloaded options" test.
	 */
	public const LIMIT_BYTES = 800000;

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Autoloaded options', 'sitewarden' );
	}

	public function group(): string {
		return Group::DATABASE;
	}

	public function run( Context $context ): array {
		$size = 0;
		// Values are the stored, serialized strings.
		foreach ( (array) $this->options->wp_load_alloptions() as $value ) {
			$size += is_string( $value ) ? strlen( $value ) : 0;
		}

		if ( $size <= self::LIMIT_BYTES ) {
			return [ Result::pass( self::ID, __( 'Autoloaded options are a reasonable size.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				/* translators: %s: size in kilobytes. */
				sprintf( __( 'Autoloaded options take %s KB', 'sitewarden' ), number_format( $size / 1024 ) ),
				__( 'Look for options left by plugins you no longer use, and ask the authors of active plugins about large ones. Tools > Site Health shows the same problem.', 'sitewarden' ),
				[],
				__( 'WordPress loads these options on every request. A large set slows the site and makes it easier to overload.', 'sitewarden' )
			),
		];
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Debug mode showing PHP errors to visitors on a production site.
 */
class DebugDisplayCheck implements Check {
	public const ID = 'config.debug_display';

	/**
	 * @var Constants
	 */
	private $constants;

	/**
	 * @var Environment
	 */
	private $environment;

	public function __construct( ?Constants $constants = null ) {
		$this->constants   = $constants ?? new Constants();
		$this->environment = ServiceFactory::create_environment();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Debug output', 'sitewarden' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		// WP_DEBUG_DISPLAY defaults to true: errors show whenever WP_DEBUG is on.
		$displays = $this->constants->is_true( 'WP_DEBUG' ) && false !== $this->constants->get( 'WP_DEBUG_DISPLAY', true );
		if ( ! $displays ) {
			return [ Result::pass( self::ID, __( 'PHP errors are not shown to visitors.', 'sitewarden' ) ) ];
		}

		$production = 'production' === $this->environment->wp_get_environment_type();
		return [
			Result::fail(
				self::ID,
				$production ? Result::SEVERITY_HIGH : Result::SEVERITY_LOW,
				__( 'PHP errors are shown to visitors', 'sitewarden' ),
				__( 'In wp-config.php, set WP_DEBUG to false, or keep it on and set WP_DEBUG_DISPLAY to false and WP_DEBUG_LOG to a path outside the web root.', 'sitewarden' ),
				[],
				__( 'Error messages reveal file paths, plugin names and other details that help attackers.', 'sitewarden' )
			),
		];
	}
}

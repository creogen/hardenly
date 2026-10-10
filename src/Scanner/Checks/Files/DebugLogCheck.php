<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Constants;
use Hardenly\Site\Filesystem;
use Hardenly\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * The debug log in its default place, wp-content/debug.log, where visitors may read it.
 */
class DebugLogCheck implements Check {
	public const ID = 'files.debug_log';

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var Constants
	 */
	private $constants;

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Urls
	 */
	private $urls;

	public function __construct( ?Filesystem $filesystem = null, ?Constants $constants = null, ?Loopback $loopback = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->constants  = $constants ?? new Constants();
		$this->loopback   = $loopback ?? new Loopback();
		$this->urls       = ServiceFactory::create_urls();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Debug log', 'hardenly' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		$content = rtrim( (string) $this->constants->get( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' ), '/\\' );
		if ( ! $this->filesystem->is_file( $content . '/debug.log' ) ) {
			return [ Result::pass( self::ID, __( 'There is no debug log in wp-content.', 'hardenly' ) ) ];
		}

		$response = $this->loopback->get( (string) $this->urls->content_url( 'debug.log' ) );
		$public   = null !== $response && 200 === $response['code'];

		return [
			Result::fail(
				self::ID,
				$public ? Result::SEVERITY_HIGH : Result::SEVERITY_LOW,
				$public
					? __( 'The debug log can be downloaded by anyone', 'hardenly' )
					: __( 'The debug log is in the wp-content folder', 'hardenly' ),
				__( 'Delete wp-content/debug.log. If you need logging, set WP_DEBUG_LOG in wp-config.php to a path outside the web folders.', 'hardenly' ),
				[ 'wp-content/debug.log' ],
				__( 'The log can contain file paths, database errors and other details useful to attackers.', 'hardenly' )
			),
		];
	}
}

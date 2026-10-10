<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Constants;
use Sitewarden\Site\Filesystem;
use Sitewarden\Site\Loopback;

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
		return __( 'Debug log', 'sitewarden' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		$content = rtrim( (string) $this->constants->get( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' ), '/\\' );
		if ( ! $this->filesystem->is_file( $content . '/debug.log' ) ) {
			return [ Result::pass( self::ID, __( 'There is no debug log in wp-content.', 'sitewarden' ) ) ];
		}

		$response = $this->loopback->get( (string) $this->urls->content_url( 'debug.log' ) );
		$public   = null !== $response && 200 === $response['code'];

		return [
			Result::fail(
				self::ID,
				$public ? Result::SEVERITY_HIGH : Result::SEVERITY_LOW,
				$public
					? __( 'The debug log can be downloaded by anyone', 'sitewarden' )
					: __( 'The debug log is in the wp-content folder', 'sitewarden' ),
				__( 'Delete wp-content/debug.log. If you need logging, set WP_DEBUG_LOG in wp-config.php to a path outside the web folders.', 'sitewarden' ),
				[ 'wp-content/debug.log' ],
				__( 'The log can contain file paths, database errors and other details useful to attackers.', 'sitewarden' )
			),
		];
	}
}

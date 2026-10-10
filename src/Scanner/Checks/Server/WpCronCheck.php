<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Server;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Whether WP-Cron runs from page visits (wp-cron.php open to everyone) or from a server cron job.
 */
class WpCronCheck implements Check {
	public const ID = 'server.wp_cron';

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
		return __( 'WP-Cron', 'hardenly' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		if ( $this->constants->is_true( 'DISABLE_WP_CRON' ) ) {
			return [ Result::pass( self::ID, __( 'Scheduled tasks run from a server cron job.', 'hardenly' ) ) ];
		}

		return [
			Result::info(
				self::ID,
				__( 'Scheduled tasks run on page visits', 'hardenly' ),
				__( 'This is the WordPress default and fine for most sites. On busy sites, a server cron job is more reliable, and lets you block public requests to wp-cron.php, which can be used to load the server: set DISABLE_WP_CRON to true and run wp-cron.php every few minutes. Ask your host.', 'hardenly' )
			),
		];
	}
}

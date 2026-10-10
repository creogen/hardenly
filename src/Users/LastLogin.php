<?php declare( strict_types=1 );

namespace Hardenly\Users;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Records when each user last logged in, for the inactive administrators check.
 *
 * WordPress keeps no login dates. Recording starts when the plugin is first loaded; the
 * start date tells the check whether there is enough history yet.
 */
class LastLogin {
	public const META_KEY     = 'hardenly_last_login';
	public const SINCE_OPTION = 'hardenly_tracking_since';

	/**
	 * @var Users
	 */
	private $users;

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->users   = ServiceFactory::create_users();
		$this->options = ServiceFactory::create_options();
	}

	/**
	 * Notes when recording started, once.
	 */
	public function start(): void {
		if ( false === $this->options->get_option( self::SINCE_OPTION, false ) ) {
			// Autoloaded, so checking it on each request costs no query.
			$this->options->add_option( self::SINCE_OPTION, time(), '', true );
		}
	}

	/**
	 * Handles `wp_login`.
	 *
	 * @param string $user_login Username.
	 * @param mixed  $user       The WP_User who logged in.
	 */
	public function record( $user_login, $user = null ): void {
		if ( is_object( $user ) && isset( $user->ID ) ) {
			$this->users->update_user_meta( (int) $user->ID, self::META_KEY, time() );
		}
	}

	/**
	 * When recording started, or 0 if it has not.
	 */
	public function since(): int {
		return (int) $this->options->get_option( self::SINCE_OPTION, 0 );
	}

	/**
	 * Last login of a user, or 0 if none was recorded.
	 */
	public function last_login( int $user_id ): int {
		return (int) $this->users->get_user_meta( $user_id, self::META_KEY, true );
	}
}

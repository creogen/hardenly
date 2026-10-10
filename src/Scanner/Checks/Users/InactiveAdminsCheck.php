<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Users\LastLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Administrators who have not logged in for a long time.
 */
class InactiveAdminsCheck implements Check {
	public const ID = 'users.inactive_admins';

	/**
	 * Administrators without a login for this many days are reported.
	 */
	public const DAYS = 90;

	/**
	 * @var LastLogin
	 */
	private $last_login;

	/**
	 * @var Users
	 */
	private $users;

	/**
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @param LastLogin|null $last_login Login records.
	 * @param callable|null  $clock      Returns the time; time() by default.
	 */
	public function __construct( ?LastLogin $last_login = null, ?callable $clock = null ) {
		$this->last_login = $last_login ?? new LastLogin();
		$this->users      = ServiceFactory::create_users();
		$this->clock      = $clock ?? 'time';
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Inactive administrators', 'sitewarden' );
	}

	public function group(): string {
		return Group::USERS;
	}

	public function run( Context $context ): array {
		$now    = (int) ( $this->clock )();
		$cutoff = $now - self::DAYS * DAY_IN_SECONDS;
		$since  = $this->last_login->since();

		if ( 0 === $since || $since > $cutoff ) {
			return [
				Result::skipped(
					self::ID,
					/* translators: %d: number of days. */
					sprintf( __( 'Sitewarden records logins from the day it was installed. This check runs once it has %d days of records.', 'sitewarden' ), self::DAYS )
				),
			];
		}

		$inactive = [];
		foreach ( (array) $this->users->get_users( [ 'role' => 'administrator' ] ) as $user ) {
			if ( is_object( $user ) && isset( $user->ID, $user->user_login ) && $this->last_login->last_login( (int) $user->ID ) < $cutoff ) {
				$inactive[] = (string) $user->user_login;
			}
		}

		if ( [] === $inactive ) {
			return [ Result::pass( self::ID, __( 'All administrators have logged in recently.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				sprintf(
					/* translators: 1: number of administrators, 2: number of days. */
					_n( '%1$d administrator has not logged in for %2$d days', '%1$d administrators have not logged in for %2$d days', count( $inactive ), 'sitewarden' ),
					count( $inactive ),
					self::DAYS
				),
				__( 'Remove administrator accounts nobody uses, or lower their role.', 'sitewarden' ),
				$inactive,
				__( 'Forgotten accounts often have old passwords, and nobody notices when someone else uses them.', 'sitewarden' )
			),
		];
	}
}

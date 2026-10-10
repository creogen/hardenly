<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * A user named "admin", the first name password-guessing attacks try.
 */
class AdminUsernameCheck implements Check {
	public const ID = 'users.admin_username';

	/**
	 * @var Users
	 */
	private $users;

	public function __construct() {
		$this->users = ServiceFactory::create_users();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'The "admin" username', 'hardenly' );
	}

	public function group(): string {
		return Group::USERS;
	}

	public function run( Context $context ): array {
		$user = $this->users->get_user_by( 'login', 'admin' );
		if ( false === $user ) {
			return [ Result::pass( self::ID, __( 'No user is called "admin".', 'hardenly' ) ) ];
		}

		$roles = $user->roles;
		return [
			Result::fail(
				self::ID,
				in_array( 'administrator', $roles, true ) ? Result::SEVERITY_MEDIUM : Result::SEVERITY_LOW,
				__( 'A user is called "admin"', 'hardenly' ),
				__( 'Create a new administrator with a different username, log in as that user, then delete "admin" and give its content to the new user.', 'hardenly' ),
				[],
				__( 'Password-guessing attacks try "admin" first. Without a known username, they have to guess both.', 'hardenly' )
			),
		];
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Number of administrator accounts.
 */
class AdminCountCheck implements Check {
	public const ID = 'users.admin_count';

	/**
	 * More administrators than this is reported.
	 */
	public const MAX_ADMINS = 3;

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
		return __( 'Number of administrators', 'sitewarden' );
	}

	public function group(): string {
		return Group::USERS;
	}

	public function run( Context $context ): array {
		$counts = $this->users->count_users();
		$count  = (int) ( $counts['avail_roles']['administrator'] ?? 0 );

		if ( $count <= self::MAX_ADMINS ) {
			return [ Result::pass( self::ID, __( 'Few users are administrators.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				/* translators: %d: number of administrators. */
				sprintf( _n( '%d user is an administrator', '%d users are administrators', $count, 'sitewarden' ), $count ),
				__( 'Give each person the lowest role they need, such as Editor, and remove accounts nobody uses.', 'sitewarden' ),
				[],
				__( 'Every administrator account is a way in. The fewer there are, the fewer passwords can be stolen or guessed.', 'sitewarden' )
			),
		];
	}
}

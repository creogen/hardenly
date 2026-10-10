<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Users\AdminUsernameCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class AdminUsernameCheckTest extends CheckTestCase {
	/**
	 * @param mixed $user What get_user_by() returns.
	 */
	private function check( $user ): AdminUsernameCheck {
		$users = $this->createMock( Users::class );
		$users->method( 'get_user_by' )->with( 'login', 'admin' )->willReturn( $user );
		ServiceFactory::set_custom_users( $users );
		return new AdminUsernameCheck();
	}

	public function testRun_NoAdminUser_Passes(): void {
		self::assertPasses( $this->result( $this->check( false ) ) );
	}

	public function testRun_AdminIsAdministrator_FailsMedium(): void {
		self::assertFails( $this->result( $this->check( (object) [ 'roles' => [ 'administrator' ] ] ) ), Result::SEVERITY_MEDIUM );
	}

	public function testRun_AdminIsSubscriber_FailsLow(): void {
		self::assertFails( $this->result( $this->check( (object) [ 'roles' => [ 'subscriber' ] ] ) ), Result::SEVERITY_LOW );
	}
}

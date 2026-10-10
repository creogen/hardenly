<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Users\AdminCountCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class AdminCountCheckTest extends CheckTestCase {
	private function check( int $admins ): AdminCountCheck {
		$users = $this->createMock( Users::class );
		$users->method( 'count_users' )->willReturn( [ 'avail_roles' => [ 'administrator' => $admins ] ] );
		ServiceFactory::set_custom_users( $users );
		return new AdminCountCheck();
	}

	public function testRun_FewAdmins_Passes(): void {
		self::assertPasses( $this->result( $this->check( AdminCountCheck::MAX_ADMINS ) ) );
	}

	public function testRun_ManyAdmins_FailsLow(): void {
		self::assertFails( $this->result( $this->check( AdminCountCheck::MAX_ADMINS + 1 ) ), Result::SEVERITY_LOW );
	}
}

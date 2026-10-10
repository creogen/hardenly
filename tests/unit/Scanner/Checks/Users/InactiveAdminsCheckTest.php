<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Users;

use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Users\InactiveAdminsCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;
use Sitewarden\Users\LastLogin;

class InactiveAdminsCheckTest extends CheckTestCase {
	private const NOW = 1800000000;

	/**
	 * @param int             $since  When login recording started.
	 * @param array<int, int> $logins Last login by user ID.
	 */
	private function check( int $since, array $logins ): InactiveAdminsCheck {
		$users = $this->createMock( Users::class );
		$users->method( 'get_users' )->willReturn(
			[
				(object) [
					'ID'         => 1,
					'user_login' => 'alice',
				],
				(object) [
					'ID'         => 2,
					'user_login' => 'bob',
				],
			]
		);
		ServiceFactory::set_custom_users( $users );

		$last_login = $this->createMock( LastLogin::class );
		$last_login->method( 'since' )->willReturn( $since );
		$last_login->method( 'last_login' )->willReturnCallback(
			static function ( int $id ) use ( $logins ): int {
				return $logins[ $id ] ?? 0;
			}
		);

		return new InactiveAdminsCheck(
			$last_login,
			static function (): int {
				return self::NOW;
			}
		);
	}

	public function testRun_RecordingTooShort_IsSkipped(): void {
		$result = $this->result( $this->check( self::NOW - 10 * DAY_IN_SECONDS, [] ) );

		self::assertSame( Result::STATUS_SKIPPED, $result->status() );
	}

	public function testRun_AllRecentlyActive_Passes(): void {
		$recent = self::NOW - DAY_IN_SECONDS;

		self::assertPasses(
			$this->result(
				$this->check(
					self::NOW - 200 * DAY_IN_SECONDS,
					[
						1 => $recent,
						2 => $recent,
					]
				)
			)
		);
	}

	public function testRun_AdminWithoutRecentLogin_FailsLowAndNamesThem(): void {
		$result = $this->result( $this->check( self::NOW - 200 * DAY_IN_SECONDS, [ 1 => self::NOW - DAY_IN_SECONDS ] ) );

		self::assertFails( $result, Result::SEVERITY_LOW );
		self::assertSame( [ 'bob' ], $result->details() );
	}
}

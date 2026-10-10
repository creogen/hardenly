<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Config;

use Sitewarden\Scanner\Checks\Config\SaltsCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class SaltsCheckTest extends CheckTestCase {
	/**
	 * @return array<string, string>
	 */
	private static function strong_keys(): array {
		$keys = [];
		foreach ( SaltsCheck::KEYS as $i => $name ) {
			$keys[ $name ] = str_repeat( chr( 65 + $i ), 64 );
		}
		return $keys;
	}

	public function testRun_StrongUniqueKeys_Passes(): void {
		self::assertPasses( $this->result( new SaltsCheck( $this->constants( self::strong_keys() ) ) ) );
	}

	public function testRun_PlaceholderKey_FailsHighAndNamesIt(): void {
		$keys             = self::strong_keys();
		$keys['AUTH_KEY'] = 'put your unique phrase here';

		$result = $this->result( new SaltsCheck( $this->constants( $keys ) ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'AUTH_KEY' ], $result->details() );
	}

	public function testRun_MissingKeys_ReportsAll(): void {
		$result = $this->result( new SaltsCheck( $this->constants( [] ) ) );

		self::assertSame( SaltsCheck::KEYS, $result->details() );
	}

	public function testRun_RepeatedKey_IsReported(): void {
		$keys              = self::strong_keys();
		$keys['NONCE_KEY'] = $keys['AUTH_KEY'];

		self::assertSame( [ 'NONCE_KEY' ], $this->result( new SaltsCheck( $this->constants( $keys ) ) )->details() );
	}
}

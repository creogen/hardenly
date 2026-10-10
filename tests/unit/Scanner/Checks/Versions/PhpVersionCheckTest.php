<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Versions;

use Sitewarden\Scanner\Checks\Versions\PhpVersionCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class PhpVersionCheckTest extends CheckTestCase {
	private function check( string $version, string $today ): PhpVersionCheck {
		return new PhpVersionCheck(
			$version,
			static function () use ( $today ): int {
				return (int) strtotime( $today . ' 12:00:00 UTC' );
			}
		);
	}

	public function testRun_SupportedBranch_Passes(): void {
		self::assertPasses( $this->result( $this->check( '8.4.3', '2026-10-10' ) ) );
	}

	public function testRun_EndedBranch_FailsHigh(): void {
		self::assertFails( $this->result( $this->check( '8.1.30', '2026-10-10' ) ), Result::SEVERITY_HIGH );
	}

	public function testRun_OlderThanTheTable_FailsHigh(): void {
		self::assertFails( $this->result( $this->check( '7.3.0', '2026-10-10' ) ), Result::SEVERITY_HIGH );
	}

	public function testRun_SupportEndsSoon_FailsLow(): void {
		self::assertFails( $this->result( $this->check( '8.2.20', '2026-10-10' ) ), Result::SEVERITY_LOW );
	}

	public function testRun_NewerThanTheTable_Passes(): void {
		self::assertPasses( $this->result( $this->check( '9.0.0', '2026-10-10' ) ) );
	}
}

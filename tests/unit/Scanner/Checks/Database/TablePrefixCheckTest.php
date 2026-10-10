<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Database;

use Hardenly\Scanner\Checks\Database\TablePrefixCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class TablePrefixCheckTest extends CheckTestCase {
	public function testRun_DefaultPrefix_FailsLow(): void {
		self::assertFails( $this->result( new TablePrefixCheck( 'wp_' ) ), Result::SEVERITY_LOW );
	}

	public function testRun_CustomPrefix_Passes(): void {
		self::assertPasses( $this->result( new TablePrefixCheck( 'site7_' ) ) );
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Database;

use Sitewarden\Scanner\Checks\Database\TablePrefixCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class TablePrefixCheckTest extends CheckTestCase {
	public function testRun_DefaultPrefix_FailsLow(): void {
		self::assertFails( $this->result( new TablePrefixCheck( 'wp_' ) ), Result::SEVERITY_LOW );
	}

	public function testRun_CustomPrefix_Passes(): void {
		self::assertPasses( $this->result( new TablePrefixCheck( 'site7_' ) ) );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Server;

use Hardenly\Scanner\Checks\Server\WpCronCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class WpCronCheckTest extends CheckTestCase {
	public function testRun_ServerCron_Passes(): void {
		self::assertPasses( $this->result( new WpCronCheck( $this->constants( [ 'DISABLE_WP_CRON' => true ] ) ) ) );
	}

	public function testRun_DefaultCron_IsANoteNotAProblem(): void {
		$result = $this->result( new WpCronCheck( $this->constants( [] ) ) );

		self::assertSame( Result::STATUS_INFO, $result->status() );
	}
}

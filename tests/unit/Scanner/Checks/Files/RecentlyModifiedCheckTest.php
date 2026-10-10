<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Files;

use Hardenly\Scanner\Checks\Files\RecentlyModifiedCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class RecentlyModifiedCheckTest extends CheckTestCase {
	public function testRun_RecentAndOldFiles_ListsOnlyRecentPhpAsANote(): void {
		$root = $this->make_dir(
			[
				'wp-content/plugins/x/new.php' => '',
				'wp-content/plugins/x/old.php' => '',
				'wp-content/plugins/x/new.js'  => '',
			]
		);
		touch( $root . '/wp-content/plugins/x/old.php', time() - 30 * DAY_IN_SECONDS );

		$result = $this->result( new RecentlyModifiedCheck( null, $root ) );

		self::assertSame( Result::STATUS_INFO, $result->status() );
		self::assertSame( [ 'wp-content/plugins/x/new.php' ], $result->details() );
	}

	public function testRun_LongerThreshold_IncludesOlderFiles(): void {
		$root = $this->make_dir( [ 'old.php' => '' ] );
		touch( $root . '/old.php', time() - 30 * DAY_IN_SECONDS );

		self::assertSame( 1, $this->result( new RecentlyModifiedCheck( null, $root ), [ 'modified_days' => 60 ] )->details_total() );
	}

	public function testRun_NothingRecent_Passes(): void {
		$root = $this->make_dir( [ 'old.php' => '' ] );
		touch( $root . '/old.php', time() - 30 * DAY_IN_SECONDS );

		self::assertPasses( $this->result( new RecentlyModifiedCheck( null, $root ) ) );
	}
}

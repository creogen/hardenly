<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Files;

use Sitewarden\Scanner\Checks\Files\BackupFilesCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class BackupFilesCheckTest extends CheckTestCase {
	/**
	 * @param array<string, string> $files Files under the WordPress root.
	 */
	private function check( array $files ): BackupFilesCheck {
		$root = $this->make_dir( $files + [ 'wp-content/index.php' => '' ] );
		return new BackupFilesCheck( null, $this->constants( [ 'WP_CONTENT_DIR' => $root . '/wp-content' ] ), $root );
	}

	public function testRun_NoBackups_Passes(): void {
		self::assertPasses(
			$this->result(
				$this->check(
					[
						'index.php'     => '',
						'wp-config.php' => '',
					]
				)
			)
		);
	}

	public function testRun_DatabaseDump_FailsHigh(): void {
		$result = $this->result( $this->check( [ 'backup-2026.sql.gz' => '' ] ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'backup-2026.sql.gz' ], $result->details() );
	}

	public function testRun_ConfigCopy_FailsHigh(): void {
		self::assertFails( $this->result( $this->check( [ 'wp-config.php.bak' => '' ] ) ), Result::SEVERITY_HIGH );
	}

	public function testRun_ArchiveInWpContent_FailsMedium(): void {
		$result = $this->result( $this->check( [ 'wp-content/site.zip' => '' ] ) );

		self::assertFails( $result, Result::SEVERITY_MEDIUM );
		self::assertSame( [ 'wp-content/site.zip' ], $result->details() );
	}

	public function testRun_ArchiveInSubfolder_IsNotReported(): void {
		self::assertPasses( $this->result( $this->check( [ 'wp-content/plugins/x/assets.zip' => '' ] ) ) );
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Filesystem as FilesystemService;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Files\UploadsPhpCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class UploadsPhpCheckTest extends CheckTestCase {
	/**
	 * @param array<string, string> $files Files under the WordPress root.
	 */
	private function check( array $files ): UploadsPhpCheck {
		$root     = $this->make_dir( $files + [ 'wp-content/uploads/.keep' => '' ] );
		$wp_files = $this->createMock( FilesystemService::class );
		$wp_files->method( 'wp_upload_dir' )->with( null, false )->willReturn( [ 'basedir' => $root . '/wp-content/uploads' ] );
		ServiceFactory::set_custom_filesystem( $wp_files );
		return new UploadsPhpCheck( null, $root );
	}

	public function testRun_OnlyMedia_Passes(): void {
		self::assertPasses( $this->result( $this->check( [ 'wp-content/uploads/2026/03/photo.jpg' => 'jpg' ] ) ) );
	}

	public function testRun_PhpFiles_FailsHighWithPathsFromTheWordPressRoot(): void {
		$result = $this->result(
			$this->check(
				[
					'wp-content/uploads/2026/03/cache.php' => '<?php eval( $_POST["x"] );',
					'wp-content/uploads/shell.PHTML'       => '<?php system( "id" );',
				]
			)
		);

		self::assertFails( $result, Result::SEVERITY_HIGH );
		$details = $result->details();
		sort( $details );
		self::assertSame( [ 'wp-content/uploads/2026/03/cache.php', 'wp-content/uploads/shell.PHTML' ], $details );
	}

	public function testRun_SilenceIsGoldenIndex_IsIgnored(): void {
		$result = $this->result(
			$this->check(
				[
					'wp-content/uploads/index.php'         => "<?php\n// Silence is golden.\n",
					'wp-content/uploads/wpforms/index.php' => '<?php /* Nothing here. */ ?>',
				]
			)
		);

		self::assertPasses( $result );
	}

	public function testRun_FileToSkip_IsNotReported(): void {
		$check = $this->check( [ 'wp-content/uploads/plugin/needed.php' => '<?php echo 1;' ] );

		self::assertPasses( $this->result( $check, [ 'skip_files' => [ 'wp-content/uploads/plugin/needed.php' ] ] ) );
	}
}

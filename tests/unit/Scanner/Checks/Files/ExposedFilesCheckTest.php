<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Files\ExposedFilesCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class ExposedFilesCheckTest extends CheckTestCase {
	protected function setUp(): void {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'site_url' )->willReturnCallback(
			static function ( string $path ): string {
				return 'https://example.com' . $path;
			}
		);
		ServiceFactory::set_custom_urls( $urls );
	}

	public function testRun_NoFiles_Passes(): void {
		self::assertPasses( $this->result( new ExposedFilesCheck( null, $this->loopback( [] ), $this->make_dir( [ 'index.php' => '' ] ) ) ) );
	}

	public function testRun_ReadableGitFolder_FailsCritical(): void {
		$root     = $this->make_dir( [ '.git/HEAD' => 'ref: refs/heads/main' ] );
		$loopback = $this->loopback(
			[
				'https://example.com/.git/HEAD' => [
					'code' => 200,
					'body' => 'ref: refs/heads/main',
				],
			]
		);

		$result = $this->result( new ExposedFilesCheck( null, $loopback, $root ) );

		self::assertFails( $result, Result::SEVERITY_CRITICAL );
		self::assertSame( [ '.git' ], $result->details() );
	}

	public function testRun_EnvFileServedAsSitePage_FailsLowOnly(): void {
		$root     = $this->make_dir( [ '.env' => 'DB_PASSWORD=x' ] );
		$loopback = $this->loopback(
			[
				'https://example.com/.env' => [
					'code' => 200,
					'body' => '<html><body>Not found page</body></html>',
				],
			]
		);

		self::assertFails( $this->result( new ExposedFilesCheck( null, $loopback, $root ) ), Result::SEVERITY_LOW );
	}
}

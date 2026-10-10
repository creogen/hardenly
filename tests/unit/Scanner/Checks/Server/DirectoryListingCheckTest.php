<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Filesystem;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Server\DirectoryListingCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class DirectoryListingCheckTest extends CheckTestCase {
	private const UPLOADS = 'https://example.com/wp-content/uploads/';

	private function check( int $code, string $body ): DirectoryListingCheck {
		$filesystem = $this->createMock( Filesystem::class );
		$filesystem->method( 'wp_upload_dir' )->willReturn( [ 'baseurl' => rtrim( self::UPLOADS, '/' ) ] );
		ServiceFactory::set_custom_filesystem( $filesystem );
		return new DirectoryListingCheck(
			$this->loopback(
				[
					self::UPLOADS => [
						'code' => $code,
						'body' => $body,
					],
				]
			)
		);
	}

	public function testRun_ListingShown_FailsMedium(): void {
		self::assertFails( $this->result( $this->check( 200, '<html><title>Index of /wp-content/uploads</title>' ) ), Result::SEVERITY_MEDIUM );
	}

	public function testRun_EmptyPage_Passes(): void {
		self::assertPasses( $this->result( $this->check( 200, '' ) ) );
	}

	public function testRun_Forbidden_Passes(): void {
		self::assertPasses( $this->result( $this->check( 403, '<title>Index of</title>' ) ) );
	}
}

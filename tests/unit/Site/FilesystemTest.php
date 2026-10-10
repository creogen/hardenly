<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Site;

use Sitewarden\Site\Filesystem;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class FilesystemTest extends CheckTestCase {
	public function testListDir_Always_ReturnsSortedNamesWithoutDots(): void {
		$root = $this->make_dir(
			[
				'b.php'     => '',
				'a.php'     => '',
				'dir/c.php' => '',
			]
		);

		self::assertSame( [ 'a.php', 'b.php', 'dir' ], ( new Filesystem() )->list_dir( $root ) );
	}

	public function testListDir_Missing_ReturnsNothing(): void {
		self::assertSame( [], ( new Filesystem() )->list_dir( '/nonexistent-' . uniqid() ) );
	}

	public function testRead_Limit_ReadsOnlyTheStart(): void {
		$root = $this->make_dir( [ 'big.txt' => str_repeat( 'x', 100 ) ] );

		self::assertSame( 'xxxxx', ( new Filesystem() )->read( $root . '/big.txt', 5 ) );
	}

	public function testRead_Missing_ReturnsNull(): void {
		self::assertNull( ( new Filesystem() )->read( '/nonexistent-' . uniqid() ) );
	}

	/**
	 * @requires OS Darwin|Linux
	 */
	public function testPermissions_File_ReturnsPermissionBits(): void {
		$root = $this->make_dir( [ 'a.php' => '' ] );
		chmod( $root . '/a.php', 0640 );

		self::assertSame( 0640, ( new Filesystem() )->permissions( $root . '/a.php' ) );
	}
}

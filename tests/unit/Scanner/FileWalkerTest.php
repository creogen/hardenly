<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use Hardenly\Scanner\FileWalker;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class FileWalkerTest extends CheckTestCase {
	private const FILES = [
		'a.php'           => '',
		'b.txt'           => '',
		'dir/c.php'       => '',
		'dir/sub/d.php'   => '',
		'skip/e.php'      => '',
		'other/f.php'     => '',
		'other/g.php'     => '',
		'other/h/i/j.php' => '',
	];

	/**
	 * Walks the whole tree, stopping after every $every files, and returns the visited paths.
	 *
	 * @param string[] $skip Paths to skip.
	 *
	 * @return string[]
	 */
	private function walk_all( string $root, int $every, array $skip = [] ): array {
		$walker  = new FileWalker();
		$visited = [];
		$cursor  = null;
		$calls   = 0;
		do {
			$seen   = 0;
			$cursor = $walker->walk(
				$root,
				$cursor,
				static function ( string $relative ) use ( &$visited, &$seen ): void {
					$visited[] = $relative;
					++$seen;
				},
				static function () use ( &$seen, $every ): bool {
					return $seen >= $every;
				},
				$skip
			);
			// The cursor must survive storage between requests.
			$cursor = null === $cursor ? null : json_decode( (string) json_encode( $cursor ), true );
			self::assertLessThan( 100, ++$calls );
		} while ( null !== $cursor );

		sort( $visited );
		return $visited;
	}

	/**
	 * @return string[]
	 */
	private static function all_files(): array {
		$files = array_keys( self::FILES );
		sort( $files );
		return $files;
	}

	public function testWalk_NoStops_VisitsEveryFile(): void {
		$root = $this->make_dir( self::FILES );

		self::assertSame( self::all_files(), $this->walk_all( $root, PHP_INT_MAX ) );
	}

	public function testWalk_StopsAfterEachFile_VisitsEveryFileOnce(): void {
		$root = $this->make_dir( self::FILES );

		self::assertSame( self::all_files(), $this->walk_all( $root, 1 ) );
	}

	public function testWalk_SkipList_LeavesThoseOut(): void {
		$root = $this->make_dir( self::FILES );

		$visited = $this->walk_all( $root, 2, [ 'skip', 'other/g.php' ] );

		self::assertNotContains( 'skip/e.php', $visited );
		self::assertNotContains( 'other/g.php', $visited );
		self::assertContains( 'other/f.php', $visited );
	}

	public function testWalk_SymbolicLink_IsNotFollowed(): void {
		$outside = $this->make_dir( [ 'secret.php' => '' ] );
		$root    = $this->make_dir( [ 'a.php' => '' ] );
		symlink( $outside, $root . '/link' );
		symlink( $outside . '/secret.php', $root . '/file-link.php' );

		self::assertSame( [ 'a.php' ], $this->walk_all( $root, PHP_INT_MAX ) );
	}

	public function testWalk_MissingRoot_FinishesAtOnce(): void {
		self::assertSame( [], $this->walk_all( sys_get_temp_dir() . '/hardenly-missing-' . uniqid(), 1 ) );
	}
}

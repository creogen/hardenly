<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Integrity;

use Sitewarden\Integrity\ChecksumComparer;
use Sitewarden\Scanner\Context;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class ChecksumComparerTest extends CheckTestCase {
	/**
	 * Runs the comparison to the end, yielding after every file when $yields is true.
	 *
	 * @param array<string, string[]> $checksums Checksums.
	 * @param string[]                $walk      Folders to search for added files.
	 *
	 * @return array<string, mixed>
	 */
	private function compare( string $root, array $checksums, array $walk, bool $yields ): array {
		$comparer = new ChecksumComparer();
		$progress = ChecksumComparer::start();
		for ( $i = 0; $i < 100 && ! $progress['done']; $i++ ) {
			$deadline = $yields ? 0.0 : PHP_FLOAT_MAX;
			$progress = $comparer->step( $root, $checksums, $walk, $progress, new Context( [], null, $deadline ), 'x/' );
			$progress = json_decode( (string) json_encode( $progress ), true );
		}
		self::assertTrue( $progress['done'] );
		return $progress;
	}

	/**
	 * @return array{string, array<string, string[]>}
	 */
	private function site(): array {
		$root      = $this->make_dir(
			[
				'a.php'         => 'original a',
				'b.php'         => 'changed b',
				'dir/c.php'     => 'original c',
				'dir/extra.php' => 'added',
				'dir/notes.txt' => 'added, not PHP',
			]
		);
		$checksums = [
			'a.php'     => [ md5( 'original a' ) ],
			'b.php'     => [ md5( 'original b' ) ],
			'dir/c.php' => [ 'something else', md5( 'original c' ) ],
			'gone.php'  => [ md5( 'gone' ) ],
		];
		return [ $root, $checksums ];
	}

	/**
	 * @dataProvider yields
	 */
	public function testStep_ToTheEnd_FindsModifiedMissingAndAddedFiles( bool $yields ): void {
		list( $root, $checksums ) = $this->site();

		$progress = $this->compare( $root, $checksums, [ 'dir' ], $yields );

		self::assertSame( [ 'x/b.php' ], $progress['modified'] );
		self::assertSame( [ 'x/gone.php' ], $progress['missing'] );
		self::assertSame( [ 'x/dir/extra.php' ], $progress['added'] );
		self::assertSame( 1, $progress['added_total'] );
	}

	/**
	 * @return array<string, array{bool}>
	 */
	public function yields(): array {
		return [
			'in one step'         => [ false ],
			'yielding every file' => [ true ],
		];
	}

	public function testStep_NoFoldersToWalk_SkipsTheSearch(): void {
		list( $root, $checksums ) = $this->site();

		self::assertSame( [], $this->compare( $root, $checksums, [], false )['added'] );
	}
}

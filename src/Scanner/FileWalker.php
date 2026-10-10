<?php declare( strict_types=1 );

namespace Sitewarden\Scanner;

use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Walks a directory tree in steps that can stop and resume in a later request.
 *
 * Symbolic links are never followed, so the walk stays inside the root. The cursor holds the
 * directories still to visit and the position in the current one; it is stored between
 * batches, so it holds relative paths only.
 */
class FileWalker {
	/**
	 * @var Filesystem
	 */
	private $filesystem;

	public function __construct( ?Filesystem $filesystem = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
	}

	/**
	 * Visits files until done or until $should_stop says to stop.
	 *
	 * @param string                    $root        Directory to walk.
	 * @param array<string, mixed>|null $cursor      Cursor from a previous call, or null to start.
	 * @param callable                  $visit       Called with ( relative path, absolute path ) for each file.
	 * @param callable                  $should_stop Called after each entry; true stops the walk.
	 * @param string[]                  $skip        Relative paths of files and directories to leave out.
	 *
	 * @return array<string, mixed>|null Cursor to continue from, or null when the walk is complete.
	 */
	public function walk( string $root, ?array $cursor, callable $visit, callable $should_stop, array $skip = [] ): ?array {
		$root    = rtrim( $root, '/\\' );
		$pending = isset( $cursor['pending'] ) && is_array( $cursor['pending'] ) ? $cursor['pending'] : [ '' ];
		$dir     = isset( $cursor['dir'] ) && is_string( $cursor['dir'] ) ? $cursor['dir'] : null;
		$offset  = isset( $cursor['offset'] ) ? (int) $cursor['offset'] : 0;
		$skip    = array_flip( array_map( [ $this, 'normalize' ], $skip ) );

		while ( true ) {
			if ( null === $dir ) {
				if ( [] === $pending ) {
					return null;
				}
				$dir    = (string) array_pop( $pending );
				$offset = 0;
			}

			$names = $this->filesystem->list_dir( '' === $dir ? $root : $root . '/' . $dir );
			$count = count( $names );
			for ( $i = $offset; $i < $count; $i++ ) {
				$relative = '' === $dir ? $names[ $i ] : $dir . '/' . $names[ $i ];
				$absolute = $root . '/' . $relative;

				if ( ! isset( $skip[ $relative ] ) && ! $this->filesystem->is_link( $absolute ) ) {
					if ( $this->filesystem->is_dir( $absolute ) ) {
						$pending[] = $relative;
					} else {
						$visit( $relative, $absolute );
					}
				}

				if ( $should_stop() ) {
					return [
						'pending' => $pending,
						'dir'     => $dir,
						'offset'  => $i + 1,
					];
				}
			}
			$dir = null;
		}
	}

	private function normalize( string $path ): string {
		return trim( str_replace( '\\', '/', $path ), '/' );
	}
}

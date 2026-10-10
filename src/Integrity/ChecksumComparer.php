<?php declare( strict_types=1 );

namespace Hardenly\Integrity;

use Hardenly\Scanner\Context;
use Hardenly\Scanner\FileWalker;
use Hardenly\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Compares files with official checksums, in steps that can pause and resume.
 *
 * First it checks each listed file (modified or missing), then it walks the given folders
 * for PHP files that the official package does not have. Progress is a small array the
 * caller keeps in its cursor; the checksums themselves are passed in on every call.
 */
class ChecksumComparer {
	/**
	 * Paths kept per list; the rest are counted.
	 */
	public const MAX_PATHS = 500;

	public const PHP_EXTENSIONS = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'inc' ];

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var FileWalker
	 */
	private $walker;

	public function __construct( ?Filesystem $filesystem = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->walker     = new FileWalker( $this->filesystem );
	}

	/**
	 * Progress before the first step.
	 *
	 * @return array<string, mixed>
	 */
	public static function start(): array {
		return [
			'offset'         => 0,
			'dir'            => 0,
			'walk'           => null,
			'modified'       => [],
			'modified_total' => 0,
			'added'          => [],
			'added_total'    => 0,
			'missing'        => [],
			'missing_total'  => 0,
			'done'           => false,
		];
	}

	/**
	 * Works until done or until the context says to yield.
	 *
	 * @param string                  $root     Folder the checksum paths are relative to.
	 * @param array<string, string[]> $checksums Allowed MD5 hashes by relative path.
	 * @param string[]                $walk     Folders, relative to $root, to search for added PHP files.
	 * @param array<string, mixed>    $progress Progress from start() or the previous step.
	 * @param Context                 $context  Time budget.
	 * @param string                  $prefix   Prepended to reported paths.
	 *
	 * @return array<string, mixed> Progress; `done` is true when finished.
	 */
	public function step( string $root, array $checksums, array $walk, array $progress, Context $context, string $prefix = '' ): array {
		$root  = rtrim( $root, '/\\' );
		$paths = array_keys( $checksums );
		sort( $paths );
		$count = count( $paths );

		while ( $progress['offset'] < $count ) {
			$path = $paths[ $progress['offset'] ];
			$hash = $this->filesystem->md5( $root . '/' . $path );
			if ( null === $hash ) {
				$progress = self::add( $progress, 'missing', $prefix . $path );
			} elseif ( ! in_array( $hash, $checksums[ $path ], true ) ) {
				$progress = self::add( $progress, 'modified', $prefix . $path );
			}
			++$progress['offset'];
			if ( $context->should_yield() ) {
				return $progress;
			}
		}

		$walk_count = count( $walk );
		while ( $progress['dir'] < $walk_count ) {
			$dir    = trim( $walk[ $progress['dir'] ], '/' );
			$cursor = $this->walker->walk(
				'' === $dir ? $root : $root . '/' . $dir,
				$progress['walk'],
				function ( string $relative ) use ( $checksums, $dir, $prefix, &$progress ): void {
					$path = '' === $dir ? $relative : $dir . '/' . $relative;
					if ( ! isset( $checksums[ $path ] ) && in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), self::PHP_EXTENSIONS, true ) ) {
						$progress = self::add( $progress, 'added', $prefix . $path );
					}
				},
				[ $context, 'should_yield' ]
			);
			if ( null !== $cursor ) {
				$progress['walk'] = $cursor;
				return $progress;
			}
			$progress['walk'] = null;
			++$progress['dir'];
		}

		$progress['done'] = true;
		return $progress;
	}

	/**
	 * @param array<string, mixed> $progress Progress.
	 * @param string               $kind     modified, added or missing.
	 * @param string               $path     Path to add.
	 *
	 * @return array<string, mixed>
	 */
	private static function add( array $progress, string $kind, string $path ): array {
		++$progress[ $kind . '_total' ];
		if ( count( $progress[ $kind ] ) < self::MAX_PATHS ) {
			$progress[ $kind ][] = $path;
		}
		return $progress;
	}
}

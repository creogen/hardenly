<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\FileWalker;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * A check that walks a directory tree over as many batches as it needs.
 *
 * Subclasses pick the root and inspect each file; what they report about matching files is
 * kept in the cursor between batches (capped) and turned into results when the walk is done.
 */
abstract class FileWalkCheck implements Check {
	/**
	 * Matching paths kept between batches; the rest are only counted.
	 */
	public const MAX_MATCHES = 500;

	/**
	 * @var Filesystem
	 */
	protected $filesystem;

	/**
	 * @var FileWalker
	 */
	private $walker;

	/**
	 * @var string
	 */
	protected $abspath;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param string|null     $abspath    WordPress root; ABSPATH by default.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?string $abspath = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->walker     = new FileWalker( $this->filesystem );
		$this->abspath    = rtrim( $abspath ?? ABSPATH, '/\\' );
	}

	public function group(): string {
		return Group::FILES;
	}

	/**
	 * Directory to walk, or null when it does not exist.
	 */
	abstract protected function root( Context $context ): ?string;

	/**
	 * Inspects a file and says what to report about it, if anything.
	 *
	 * @param string  $path     Path relative to the WordPress root, for reporting.
	 * @param string  $absolute Absolute path.
	 * @param Context $context  Settings of the run.
	 *
	 * @return string|null What to report (usually the path), or null when the file is fine.
	 */
	abstract protected function inspect( string $path, string $absolute, Context $context ): ?string;

	/**
	 * Results once the walk is done.
	 *
	 * @param string[] $matches What inspect() reported, capped at MAX_MATCHES.
	 * @param int      $total   Number of matching files.
	 *
	 * @return Result[]
	 */
	abstract protected function results( array $matches, int $total ): array;

	public function run( Context $context ): array {
		$root = $this->root( $context );
		if ( null === $root || ! $this->filesystem->is_dir( $root ) ) {
			return $this->results( [], 0 );
		}
		$root   = rtrim( $root, '/\\' );
		$prefix = $this->prefix( $root );

		$cursor  = $context->cursor() ?? [];
		$matches = isset( $cursor['matches'] ) && is_array( $cursor['matches'] ) ? $cursor['matches'] : [];
		$total   = (int) ( $cursor['total'] ?? 0 );

		$next = $this->walker->walk(
			$root,
			isset( $cursor['walk'] ) && is_array( $cursor['walk'] ) ? $cursor['walk'] : null,
			function ( string $relative, string $absolute ) use ( $context, $prefix, &$matches, &$total ): void {
				$match = $this->inspect( $prefix . $relative, $absolute, $context );
				if ( null !== $match ) {
					++$total;
					if ( count( $matches ) < self::MAX_MATCHES ) {
						$matches[] = $match;
					}
				}
			},
			[ $context, 'should_yield' ],
			$this->skip( $context, $prefix )
		);

		if ( null !== $next ) {
			$context->pause(
				[
					'walk'    => $next,
					'matches' => $matches,
					'total'   => $total,
				]
			);
			return [];
		}

		return $this->results( $matches, $total );
	}

	/**
	 * The root's path relative to the WordPress root, with a trailing slash ('' for the root).
	 */
	private function prefix( string $root ): string {
		if ( $root === $this->abspath ) {
			return '';
		}
		if ( 0 === strpos( $root, $this->abspath . '/' ) ) {
			return substr( $root, strlen( $this->abspath ) + 1 ) . '/';
		}
		return $root . '/';
	}

	/**
	 * "Files to skip" from the settings, relative to this walk's root.
	 *
	 * @return string[]
	 */
	private function skip( Context $context, string $prefix ): array {
		$skip  = $context->setting( 'skip_files', [] );
		$paths = [];
		foreach ( is_array( $skip ) ? $skip : [] as $path ) {
			$path = trim( str_replace( '\\', '/', (string) $path ), '/' );
			if ( '' === $prefix ) {
				$paths[] = $path;
			} elseif ( 0 === strpos( $path, $prefix ) ) {
				$paths[] = substr( $path, strlen( $prefix ) );
			}
		}
		return $paths;
	}

	/**
	 * Lower-case file extension.
	 */
	protected static function extension( string $path ): string {
		return strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}
}

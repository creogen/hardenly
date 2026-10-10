<?php declare( strict_types=1 );

namespace Sitewarden\Site;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- The scanner reads local files to inspect them. WP_Filesystem
// is meant for writing files and may need credentials; Sitewarden never writes, moves or deletes site files.

/**
 * Read-only access to local files. The only place the plugin calls PHP file functions.
 *
 * Paths and contents read here are untrusted: escape them on output.
 */
class Filesystem {
	/**
	 * Files larger than this are never read whole.
	 */
	public const MAX_READ_BYTES = 2 * 1024 * 1024;

	public function exists( string $path ): bool {
		return file_exists( $path );
	}

	public function is_file( string $path ): bool {
		return is_file( $path );
	}

	public function is_dir( string $path ): bool {
		return is_dir( $path );
	}

	public function is_link( string $path ): bool {
		return is_link( $path );
	}

	/**
	 * Names in a directory, sorted, without `.` and `..`.
	 *
	 * @return string[] Empty when the directory cannot be read.
	 */
	public function list_dir( string $path ): array {
		$names = is_dir( $path ) && is_readable( $path ) ? scandir( $path ) : false;
		if ( false === $names ) {
			return [];
		}
		return array_values( array_diff( $names, [ '.', '..' ] ) );
	}

	/**
	 * Modification time as a Unix timestamp, or 0 when unknown.
	 */
	public function mtime( string $path ): int {
		$time = filemtime( $path );
		return false === $time ? 0 : $time;
	}

	/**
	 * Size in bytes, or 0 when unknown.
	 */
	public function size( string $path ): int {
		$size = filesize( $path );
		return false === $size ? 0 : $size;
	}

	/**
	 * Permission bits (e.g. 0644), or null when unknown.
	 */
	public function permissions( string $path ): ?int {
		$perms = fileperms( $path );
		return false === $perms ? null : $perms & 0777;
	}

	/**
	 * MD5 hash of a file, as WordPress.org checksums list them, or null when it cannot be read.
	 */
	public function md5( string $path ): ?string {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$hash = md5_file( $path );
		return false === $hash ? null : $hash;
	}

	/**
	 * Reads up to $max_bytes from the start of a file.
	 *
	 * @param string $path      File path.
	 * @param int    $max_bytes Bytes to read at most.
	 *
	 * @return string|null Null when the file cannot be read.
	 */
	public function read( string $path, int $max_bytes = self::MAX_READ_BYTES ): ?string {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$contents = file_get_contents( $path, false, null, 0, max( 0, $max_bytes ) );
		return false === $contents ? null : $contents;
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Check groups, in the order they run: fast checks first, the file walks last.
 */
final class Group {
	public const CONFIG   = 'config';
	public const USERS    = 'users';
	public const VERSIONS = 'versions';
	public const SERVER   = 'server';
	public const DATABASE = 'database';
	public const FILES    = 'files';
	public const CODE     = 'code';
	public const ONLINE   = 'online';

	public const ORDER = [
		self::CONFIG,
		self::USERS,
		self::VERSIONS,
		self::SERVER,
		self::DATABASE,
		self::FILES,
		self::CODE,
		self::ONLINE,
	];

	/**
	 * Translated group names. Groups added by extensions show their id.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return [
			self::CONFIG   => __( 'Configuration', 'sitewarden' ),
			self::USERS    => __( 'Users', 'sitewarden' ),
			self::VERSIONS => __( 'Versions', 'sitewarden' ),
			self::SERVER   => __( 'Server & HTTP', 'sitewarden' ),
			self::DATABASE => __( 'Database', 'sitewarden' ),
			self::FILES    => __( 'Files', 'sitewarden' ),
			self::CODE     => __( 'Code patterns', 'sitewarden' ),
			self::ONLINE   => __( 'Online services', 'sitewarden' ),
		];
	}

	/**
	 * Position in the run order; unknown groups run after the known ones.
	 */
	public static function position( string $group ): int {
		$position = array_search( $group, self::ORDER, true );
		return false === $position ? count( self::ORDER ) : $position;
	}
}

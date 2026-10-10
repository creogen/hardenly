<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Key files and the WordPress folder that anyone on the server can change.
 */
class PermissionsCheck implements Check {
	public const ID = 'files.permissions';

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var string
	 */
	private $abspath;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param string|null     $abspath    WordPress root.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?string $abspath = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->abspath    = rtrim( $abspath ?? ABSPATH, '/\\' );
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'File permissions', 'sitewarden' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			return [ Result::skipped( self::ID, __( 'Windows servers do not use these permissions.', 'sitewarden' ) ) ];
		}

		$paths = [ $this->abspath, $this->config_path(), $this->abspath . '/.htaccess' ];

		$writable = [];
		foreach ( array_filter( $paths ) as $path ) {
			$perms = $this->filesystem->exists( $path ) ? $this->filesystem->permissions( $path ) : null;
			if ( null !== $perms && ( $perms & 0002 ) ) {
				$writable[] = sprintf( '%s (%o)', $this->relative( $path ), $perms );
			}
		}

		if ( [] === $writable ) {
			return [ Result::pass( self::ID, __( 'Key files cannot be changed by other users on the server.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_HIGH,
				__( 'Key files can be changed by anyone on the server', 'sitewarden' ),
				__( 'Set folders to 755 and files to 644 (wp-config.php can be 600 or 640), in your hosting file manager or over SFTP. Ask your host if unsure.', 'sitewarden' ),
				$writable,
				__( 'On shared hosting, other accounts or a compromised site next door could change these files.', 'sitewarden' )
			),
		];
	}

	/**
	 * wp-config.php, which may sit one folder above WordPress.
	 */
	private function config_path(): ?string {
		$inside = $this->abspath . '/wp-config.php';
		if ( $this->filesystem->exists( $inside ) ) {
			return $inside;
		}
		$above = dirname( $this->abspath ) . '/wp-config.php';
		return $this->filesystem->exists( $above ) && ! $this->filesystem->exists( dirname( $this->abspath ) . '/wp-settings.php' ) ? $above : null;
	}

	private function relative( string $path ): string {
		if ( $path === $this->abspath ) {
			return __( 'WordPress folder', 'sitewarden' );
		}
		return 0 === strpos( $path, $this->abspath . '/' ) ? substr( $path, strlen( $this->abspath ) + 1 ) : '../' . basename( $path );
	}
}

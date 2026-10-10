<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Constants;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Backups and archives in the WordPress and wp-content folders, where visitors may download them.
 */
class BackupFilesCheck implements Check {
	public const ID = 'files.backups';

	/**
	 * Database dumps and copies of wp-config.php: they hold passwords.
	 */
	private const SENSITIVE = '/(\.sql(\.gz|\.zip|\.bz2)?|^wp-config\.php[._~-].*|^wp-config\.(bak|old|orig|save|txt))$/i';

	private const ARCHIVE = '/\.(zip|tar|tar\.gz|tgz|gz|bz2|7z|rar|bak|old|backup|orig)$/i';

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var Constants
	 */
	private $constants;

	/**
	 * @var string
	 */
	private $abspath;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param Constants|null  $constants  Constants (WP_CONTENT_DIR).
	 * @param string|null     $abspath    WordPress root.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?Constants $constants = null, ?string $abspath = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->constants  = $constants ?? new Constants();
		$this->abspath    = rtrim( $abspath ?? ABSPATH, '/\\' );
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Backup files', 'sitewarden' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		$content = rtrim( (string) $this->constants->get( 'WP_CONTENT_DIR', $this->abspath . '/wp-content' ), '/\\' );
		$folders = array_unique( [ $this->abspath, $content ] );

		$sensitive = [];
		$archives  = [];
		foreach ( $folders as $folder ) {
			foreach ( $this->filesystem->list_dir( $folder ) as $name ) {
				$path = $folder . '/' . $name;
				if ( ! $this->filesystem->is_file( $path ) ) {
					continue;
				}
				$relative = 0 === strpos( $path, $this->abspath . '/' ) ? substr( $path, strlen( $this->abspath ) + 1 ) : $path;
				if ( 1 === preg_match( self::SENSITIVE, $name ) ) {
					$sensitive[] = $relative;
				} elseif ( 1 === preg_match( self::ARCHIVE, $name ) ) {
					$archives[] = $relative;
				}
			}
		}

		if ( [] === $sensitive && [] === $archives ) {
			return [ Result::pass( self::ID, __( 'No backups or archives in public folders.', 'sitewarden' ) ) ];
		}

		$found = array_merge( $sensitive, $archives );
		return [
			Result::fail(
				self::ID,
				[] !== $sensitive ? Result::SEVERITY_HIGH : Result::SEVERITY_MEDIUM,
				/* translators: %d: number of files. */
				sprintf( _n( '%d backup or archive file is in a public folder', '%d backup or archive files are in public folders', count( $found ), 'sitewarden' ), count( $found ) ),
				__( 'Move backups out of the web folders, to your computer or a backup service, and delete them from the server.', 'sitewarden' ),
				$found,
				__( 'Anyone who guesses the file name can download it. Database dumps and copies of wp-config.php contain passwords.', 'sitewarden' )
			),
		];
	}
}

<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Filesystem as FilesystemService;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * PHP files in the uploads folder, which should only hold media.
 */
class UploadsPhpCheck extends FileWalkCheck {
	public const ID = 'files.uploads_php';

	public const EXTENSIONS = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar' ];

	/**
	 * A file of only a PHP comment, like the index.php plugins add to block folder listing.
	 */
	private const COMMENT_ONLY = '~^\s*<\?php\s*(?://[^\n]*\s*|#[^\n]*\s*|/\*.*?\*/\s*)*(?:\?>\s*)?$~s';

	/**
	 * @var FilesystemService
	 */
	private $wp_filesystem;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param string|null     $abspath    WordPress root.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?string $abspath = null ) {
		parent::__construct( $filesystem, $abspath );
		$this->wp_filesystem = ServiceFactory::create_filesystem();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'PHP files in uploads', 'sitewarden' );
	}

	protected function root( Context $context ): ?string {
		$uploads = $this->wp_filesystem->wp_upload_dir( null, false );
		return empty( $uploads['basedir'] ) ? null : (string) $uploads['basedir'];
	}

	protected function inspect( string $path, string $absolute, Context $context ): ?string {
		if ( ! in_array( self::extension( $path ), self::EXTENSIONS, true ) ) {
			return null;
		}
		// Small files with nothing but a comment ("Silence is golden") are harmless.
		if ( $this->filesystem->size( $absolute ) <= 1024 ) {
			$contents = $this->filesystem->read( $absolute, 1024 );
			if ( null !== $contents && 1 === preg_match( self::COMMENT_ONLY, $contents ) ) {
				return null;
			}
		}
		return $path;
	}

	protected function results( array $matches, int $total ): array {
		if ( 0 === $total ) {
			return [ Result::pass( self::ID, __( 'The uploads folder has no PHP files.', 'sitewarden' ) ) ];
		}

		return [
			new Result(
				self::ID,
				Result::STATUS_FAIL,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of files. */
				sprintf( _n( '%d PHP file found in the uploads folder', '%d PHP files found in the uploads folder', $total, 'sitewarden' ), $total ),
				__( 'Check where each file came from. Remove anything you do not recognise and change your passwords. If a plugin needs the file, add it to "Files to skip". Consider blocking PHP in uploads on your server.', 'sitewarden' ),
				$matches,
				__( 'The uploads folder should only hold media. PHP files there are a common sign of a break-in or a vulnerable plugin.', 'sitewarden' ),
				'',
				$total
			),
		];
	}
}

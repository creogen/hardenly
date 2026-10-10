<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Filesystem;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * Folder listing in uploads, which shows every uploaded file to visitors.
 */
class DirectoryListingCheck implements Check {
	public const ID = 'server.directory_listing';

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	public function __construct( ?Loopback $loopback = null ) {
		$this->loopback   = $loopback ?? new Loopback();
		$this->filesystem = ServiceFactory::create_filesystem();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Folder listing', 'sitewarden' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$uploads = $this->filesystem->wp_upload_dir( null, false );
		if ( empty( $uploads['baseurl'] ) ) {
			return [ Result::skipped( self::ID, __( 'The uploads folder address is unknown.', 'sitewarden' ) ) ];
		}

		$response = $this->loopback->get( rtrim( (string) $uploads['baseurl'], '/' ) . '/' );
		if ( null === $response ) {
			return [ Result::skipped( self::ID, __( 'The uploads folder could not be reached.', 'sitewarden' ) ) ];
		}

		if ( 200 !== $response['code'] || 1 !== preg_match( '/<title>\s*Index of|<h1>\s*Index of/i', $response['body'] ) ) {
			return [ Result::pass( self::ID, __( 'Visitors cannot list the uploads folder.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_MEDIUM,
				__( 'Visitors can list the files in the uploads folder', 'sitewarden' ),
				__( 'Turn off directory listing on the server (for Apache, add "Options -Indexes" to .htaccess), or ask your host.', 'sitewarden' ),
				[ (string) $uploads['baseurl'] ],
				__( 'Listings show every uploaded file, including private documents and leftovers attackers look for.', 'sitewarden' )
			),
		];
	}
}

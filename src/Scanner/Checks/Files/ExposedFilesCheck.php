<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Filesystem;
use Hardenly\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * Version control folders and environment files in the WordPress folder, which can leak code and secrets.
 */
class ExposedFilesCheck implements Check {
	public const ID = 'files.exposed';

	/**
	 * File to look for => file to request to see if visitors can read it.
	 */
	public const FILES = [
		'.git' => '.git/HEAD',
		'.svn' => '.svn/entries',
		'.hg'  => '.hg/requires',
		'.env' => '.env',
	];

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var string
	 */
	private $abspath;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param Loopback|null   $loopback   Requests to the site.
	 * @param string|null     $abspath    WordPress root.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?Loopback $loopback = null, ?string $abspath = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
		$this->loopback   = $loopback ?? new Loopback();
		$this->urls       = ServiceFactory::create_urls();
		$this->abspath    = rtrim( $abspath ?? ABSPATH, '/\\' );
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( '.git and .env files', 'hardenly' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		$public  = [];
		$present = [];
		foreach ( self::FILES as $name => $probe ) {
			if ( ! $this->filesystem->exists( $this->abspath . '/' . $name ) ) {
				continue;
			}
			$response = $this->loopback->get( (string) $this->urls->site_url( '/' . $probe ) );
			if ( null !== $response && 200 === $response['code'] && '' !== trim( $response['body'] ) && false === stripos( $response['body'], '<html' ) ) {
				$public[] = $name;
			} else {
				$present[] = $name;
			}
		}

		if ( [] !== $public ) {
			return [
				Result::fail(
					self::ID,
					Result::SEVERITY_CRITICAL,
					__( 'Anyone can download version control or environment files', 'hardenly' ),
					__( 'Block access to these files in your server configuration right away, then move them out of the web folder. Change any passwords or keys they contain.', 'hardenly' ),
					$public,
					__( 'These files can expose the site\'s code history, database passwords and API keys.', 'hardenly' )
				),
			];
		}
		if ( [] !== $present ) {
			return [
				Result::fail(
					self::ID,
					Result::SEVERITY_LOW,
					__( 'Version control or environment files are in the WordPress folder', 'hardenly' ),
					__( 'Visitors cannot read them now, but a server change could expose them. Move them out of the web folder if you can.', 'hardenly' ),
					$present
				),
			];
		}

		return [ Result::pass( self::ID, __( 'No version control or environment files in the WordPress folder.', 'hardenly' ) ) ];
	}
}

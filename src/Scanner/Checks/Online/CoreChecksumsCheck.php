<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Online;

use Merkushin\Wpal\Service\Localization;
use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Integrity\ChecksumComparer;
use Sitewarden\Integrity\WordPressOrgChecksums;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress core files compared with the official checksums from WordPress.org (opt-in).
 */
class CoreChecksumsCheck implements Check {
	public const ID = 'online.core_checksums';

	/**
	 * Core folders searched for PHP files the official package does not have.
	 */
	private const WALK = [ 'wp-admin', 'wp-includes' ];

	/**
	 * @var WordPressOrgChecksums
	 */
	private $source;

	/**
	 * @var ChecksumComparer
	 */
	private $comparer;

	/**
	 * @var string
	 */
	private $abspath;

	/**
	 * @var SiteIdentity
	 */
	private $site;

	/**
	 * @var Localization
	 */
	private $localization;

	/**
	 * @param WordPressOrgChecksums|null $source     Checksums.
	 * @param Filesystem|null            $filesystem File access.
	 * @param string|null                $abspath    WordPress root.
	 */
	public function __construct( ?WordPressOrgChecksums $source = null, ?Filesystem $filesystem = null, ?string $abspath = null ) {
		$this->source       = $source ?? new WordPressOrgChecksums();
		$this->comparer     = new ChecksumComparer( $filesystem );
		$this->abspath      = rtrim( $abspath ?? ABSPATH, '/\\' );
		$this->site         = ServiceFactory::create_site_identity();
		$this->localization = ServiceFactory::create_localization();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'WordPress core files', 'sitewarden' );
	}

	public function group(): string {
		return Group::ONLINE;
	}

	public function run( Context $context ): array {
		if ( ! $context->setting( 'checksums_enabled', false ) ) {
			return [];
		}

		$version   = (string) $this->site->get_bloginfo( 'version' );
		$checksums = $this->source->core( $version, (string) $this->localization->get_locale() );
		if ( null === $checksums ) {
			return [ Result::skipped( self::ID, __( 'WordPress.org has no checksums for this WordPress version, or could not be reached.', 'sitewarden' ) ) ];
		}

		// Bundled themes and plugins in wp-content are updated separately; leave them out.
		$checksums = array_filter(
			$checksums,
			static function ( string $path ): bool {
				return 0 !== strpos( $path, 'wp-content/' );
			},
			ARRAY_FILTER_USE_KEY
		);

		$progress = $this->comparer->step( $this->abspath, $checksums, self::WALK, $context->cursor() ?? ChecksumComparer::start(), $context );
		if ( ! $progress['done'] ) {
			$context->pause( $progress );
			return [];
		}

		return self::results( $progress );
	}

	/**
	 * @param array<string, mixed> $progress Finished comparison.
	 *
	 * @return Result[]
	 */
	private static function results( array $progress ): array {
		$results = [];
		if ( $progress['modified_total'] > 0 ) {
			$results[] = new Result(
				self::ID,
				Result::STATUS_FAIL,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of files. */
				sprintf( _n( '%d WordPress core file differs from the official version', '%d WordPress core files differ from the official version', $progress['modified_total'], 'sitewarden' ), $progress['modified_total'] ),
				__( 'Reinstall WordPress from Dashboard > Updates ("Re-install version"). It replaces core files and keeps your content, plugins and themes. Then change your passwords if you did not edit these files yourself.', 'sitewarden' ),
				$progress['modified'],
				__( 'Core files should never change outside updates. Changed core files are a common place to hide malicious code.', 'sitewarden' ),
				'modified',
				$progress['modified_total']
			);
		}
		if ( $progress['added_total'] > 0 ) {
			$results[] = new Result(
				self::ID,
				Result::STATUS_WARNING,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of files. */
				sprintf( _n( '%d unknown PHP file in wp-admin or wp-includes', '%d unknown PHP files in wp-admin or wp-includes', $progress['added_total'], 'sitewarden' ), $progress['added_total'] ),
				__( 'These files are not part of WordPress. Check where they came from and remove them if nothing on your site needs them; reinstalling WordPress does not remove them.', 'sitewarden' ),
				$progress['added'],
				__( 'Nothing but WordPress itself should add PHP files to these folders.', 'sitewarden' ),
				'added',
				$progress['added_total']
			);
		}
		if ( $progress['missing_total'] > 0 ) {
			$results[] = new Result(
				self::ID,
				Result::STATUS_INFO,
				Result::SEVERITY_INFO,
				/* translators: %d: number of files. */
				sprintf( _n( '%d WordPress core file is missing', '%d WordPress core files are missing', $progress['missing_total'], 'sitewarden' ), $progress['missing_total'] ),
				__( 'Some sites delete files such as readme.html on purpose. If you did not, reinstall WordPress from Dashboard > Updates.', 'sitewarden' ),
				$progress['missing'],
				'',
				'missing',
				$progress['missing_total']
			);
		}

		return [] === $results ? [ Result::pass( self::ID, __( 'WordPress core files match the official version.', 'sitewarden' ) ) ] : $results;
	}
}

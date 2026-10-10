<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Files from the WordPress package that a live site does not need and that reveal its version.
 */
class LeftoverFilesCheck implements Check {
	public const ID = 'files.leftovers';

	public const FILES = [ 'readme.html', 'license.txt', 'wp-config-sample.php' ];

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
		return __( 'Leftover install files', 'sitewarden' );
	}

	public function group(): string {
		return Group::FILES;
	}

	public function run( Context $context ): array {
		$found = array_values(
			array_filter(
				self::FILES,
				function ( string $file ): bool {
					return $this->filesystem->is_file( $this->abspath . '/' . $file );
				}
			)
		);

		if ( [] === $found ) {
			return [ Result::pass( self::ID, __( 'No leftover install files.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				__( 'Install files that reveal the WordPress version are public', 'sitewarden' ),
				__( 'Block them in your server configuration, or delete them after each WordPress update (updates bring them back).', 'sitewarden' ),
				$found,
				__( 'These files tell visitors which WordPress version runs the site, which helps attackers pick known holes.', 'sitewarden' )
			),
		];
	}
}

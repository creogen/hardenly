<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Files;

use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * PHP files changed in the last days (the "Recently changed files" setting).
 *
 * Updates change files too, so this is a list to look over, not a problem on its own.
 */
class RecentlyModifiedCheck extends FileWalkCheck {
	public const ID = 'files.recently_modified';

	/**
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @param Filesystem|null $filesystem File access.
	 * @param string|null     $abspath    WordPress root.
	 * @param callable|null   $clock      Returns the time; time() by default.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?string $abspath = null, ?callable $clock = null ) {
		parent::__construct( $filesystem, $abspath );
		$this->clock = $clock ?? 'time';
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Recently changed files', 'sitewarden' );
	}

	protected function root( Context $context ): ?string {
		return $this->abspath;
	}

	protected function matches( string $relative, string $absolute, Context $context ): bool {
		if ( 'php' !== self::extension( $relative ) ) {
			return false;
		}
		$days = max( 1, (int) $context->setting( 'modified_days', 7 ) );
		return $this->filesystem->mtime( $absolute ) >= (int) ( $this->clock )() - $days * DAY_IN_SECONDS;
	}

	protected function results( array $matches, int $total ): array {
		if ( 0 === $total ) {
			return [ Result::pass( self::ID, __( 'No PHP files changed recently.', 'sitewarden' ) ) ];
		}

		return [
			new Result(
				self::ID,
				Result::STATUS_INFO,
				Result::SEVERITY_INFO,
				/* translators: %d: number of files. */
				sprintf( _n( '%d PHP file changed recently', '%d PHP files changed recently', $total, 'sitewarden' ), $total ),
				__( 'Updates change files, so most of these are expected. Look for files outside the plugins and themes you updated.', 'sitewarden' ),
				$matches,
				__( 'An unexpected change to a PHP file can be a sign of a break-in.', 'sitewarden' ),
				'',
				$total
			),
		];
	}
}

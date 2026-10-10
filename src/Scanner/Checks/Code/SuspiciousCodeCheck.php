<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Code;

use Hardenly\Scanner\Checks\Files\FileWalkCheck;
use Hardenly\Scanner\Code\PatternMatcher;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * PHP files with code patterns typical of hidden or injected code.
 *
 * Reported as warnings to review: the patterns are heuristics, and some legitimate plugins
 * match them. A file can be added to "Files to skip" after review.
 */
class SuspiciousCodeCheck extends FileWalkCheck {
	public const ID = 'code.suspicious';

	/**
	 * Only the start of larger files is read.
	 */
	public const MAX_BYTES = 1024 * 1024;

	public const EXTENSIONS = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'inc' ];

	/**
	 * Separates the severity from the detail in what inspect() reports.
	 */
	private const SEPARATOR = '|';

	/**
	 * @var PatternMatcher
	 */
	private $matcher;

	/**
	 * @param Filesystem|null     $filesystem File access.
	 * @param string|null         $abspath    WordPress root.
	 * @param PatternMatcher|null $matcher    Code patterns.
	 */
	public function __construct( ?Filesystem $filesystem = null, ?string $abspath = null, ?PatternMatcher $matcher = null ) {
		parent::__construct( $filesystem, $abspath );
		$this->matcher = $matcher ?? new PatternMatcher();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Suspicious code', 'hardenly' );
	}

	public function group(): string {
		return Group::CODE;
	}

	protected function root( Context $context ): ?string {
		return $this->abspath;
	}

	protected function inspect( string $path, string $absolute, Context $context ): ?string {
		if ( ! in_array( self::extension( $path ), self::EXTENSIONS, true ) ) {
			return null;
		}
		$code = $this->filesystem->read( $absolute, self::MAX_BYTES );
		if ( null === $code ) {
			return null;
		}

		$matches = $this->matcher->find( $code );
		if ( [] === $matches ) {
			return null;
		}

		$severity = Result::SEVERITY_MEDIUM;
		$labels   = [];
		foreach ( $matches as $match ) {
			if ( Result::SEVERITY_HIGH === $match['severity'] ) {
				$severity = Result::SEVERITY_HIGH;
			}
			$labels[] = PatternMatcher::label( $match['rule'] );
		}

		$first = $matches[0];
		return $severity . self::SEPARATOR . sprintf(
			'%s:%d %s (%s)',
			$path,
			$first['line'],
			$first['excerpt'],
			implode( '; ', $labels )
		);
	}

	protected function results( array $matches, int $total ): array {
		if ( 0 === $total ) {
			return [ Result::pass( self::ID, __( 'No suspicious code patterns found.', 'hardenly' ) ) ];
		}

		$severity = Result::SEVERITY_MEDIUM;
		$details  = [];
		foreach ( $matches as $match ) {
			list( $match_severity, $detail ) = array_pad( explode( self::SEPARATOR, $match, 2 ), 2, '' );
			if ( Result::SEVERITY_HIGH === $match_severity ) {
				$severity = Result::SEVERITY_HIGH;
			}
			$details[] = $detail;
		}

		return [
			new Result(
				self::ID,
				Result::STATUS_WARNING,
				$severity,
				/* translators: %d: number of files. */
				sprintf( _n( 'Suspicious code in %d file', 'Suspicious code in %d files', $total, 'hardenly' ), $total ),
				__( 'Compare each file with a fresh copy from its plugin or theme author (or WordPress.org). If the code is not in the original, replace the file, change all passwords and look for other changes. If it is expected, add the file to "Files to skip".', 'hardenly' ),
				$details,
				__( 'These files contain patterns often used to hide or inject code, such as code that decodes and runs itself. Some legitimate plugins use them too, so review before acting.', 'hardenly' ),
				'',
				$total
			),
		];
	}
}

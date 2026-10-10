<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Versions;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * PHP version against the end of security support of its branch.
 */
class PhpVersionCheck implements Check {
	public const ID = 'versions.php';

	/**
	 * Last day of security fixes per PHP branch, from https://www.php.net/supported-versions.php.
	 * Update with each Hardenly release.
	 */
	public const END_OF_LIFE = [
		'7.4' => '2022-11-28',
		'8.0' => '2023-11-26',
		'8.1' => '2025-12-31',
		'8.2' => '2026-12-31',
		'8.3' => '2027-12-31',
		'8.4' => '2028-12-31',
		'8.5' => '2029-12-31',
	];

	/**
	 * Support ending within this many days is reported.
	 */
	public const WARN_DAYS = 180;

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @param string|null   $version PHP version; the running one by default.
	 * @param callable|null $clock   Returns the time; time() by default.
	 */
	public function __construct( ?string $version = null, ?callable $clock = null ) {
		$this->version = $version ?? PHP_VERSION;
		$this->clock   = $clock ?? 'time';
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'PHP version', 'hardenly' );
	}

	public function group(): string {
		return Group::VERSIONS;
	}

	public function run( Context $context ): array {
		$branch = implode( '.', array_slice( explode( '.', $this->version ), 0, 2 ) );
		$oldest = array_key_first( self::END_OF_LIFE );
		$end    = self::END_OF_LIFE[ $branch ] ?? ( version_compare( $branch, (string) $oldest, '<' ) ? self::END_OF_LIFE[ $oldest ] : null );

		if ( null === $end ) {
			/* translators: %s: PHP version. */
			return [ Result::pass( self::ID, sprintf( __( 'PHP %s gets security fixes.', 'hardenly' ), $this->version ) ) ];
		}

		$end_time = (int) strtotime( $end . ' 23:59:59 UTC' );
		$now      = (int) ( $this->clock )();

		if ( $end_time < $now ) {
			return [
				Result::fail(
					self::ID,
					Result::SEVERITY_HIGH,
					/* translators: %s: PHP version. */
					sprintf( __( 'PHP %s no longer gets security fixes', 'hardenly' ), $this->version ),
					__( 'Ask your host to move the site to a supported PHP version, or change it in your hosting control panel. Test the site afterwards.', 'hardenly' ),
					[],
					__( 'Security holes found in this PHP version are no longer fixed.', 'hardenly' )
				),
			];
		}

		if ( $end_time - $now < self::WARN_DAYS * DAY_IN_SECONDS ) {
			return [
				Result::fail(
					self::ID,
					Result::SEVERITY_LOW,
					/* translators: 1: PHP version, 2: date. */
					sprintf( __( 'PHP %1$s stops getting security fixes on %2$s', 'hardenly' ), $this->version, $end ),
					__( 'Plan a move to a newer PHP version with your host.', 'hardenly' )
				),
			];
		}

		/* translators: %s: PHP version. */
		return [ Result::pass( self::ID, sprintf( __( 'PHP %s gets security fixes.', 'hardenly' ), $this->version ) ) ];
	}
}

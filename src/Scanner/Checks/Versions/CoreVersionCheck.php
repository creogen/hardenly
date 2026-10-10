<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress core updates, from the update check WordPress already runs (no requests of its own).
 */
class CoreVersionCheck implements Check {
	public const ID = 'versions.core';

	/**
	 * @var Transient
	 */
	private $transients;

	/**
	 * @var SiteIdentity
	 */
	private $site;

	public function __construct() {
		$this->transients = ServiceFactory::create_transient();
		$this->site       = ServiceFactory::create_site_identity();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'WordPress version', 'sitewarden' );
	}

	public function group(): string {
		return Group::VERSIONS;
	}

	public function run( Context $context ): array {
		$updates = $this->transients->get_site_transient( 'update_core' );
		if ( ! is_object( $updates ) || ! isset( $updates->updates ) || ! is_array( $updates->updates ) ) {
			return [ Result::skipped( self::ID, __( 'WordPress has not checked for updates yet.', 'sitewarden' ) ) ];
		}

		$installed = (string) $this->site->get_bloginfo( 'version' );
		$newest    = null;
		foreach ( $updates->updates as $offer ) {
			if ( is_object( $offer ) && 'upgrade' === ( $offer->response ?? '' ) && isset( $offer->current )
				&& version_compare( (string) $offer->current, $installed, '>' )
				&& ( null === $newest || version_compare( (string) $offer->current, $newest, '>' ) ) ) {
				$newest = (string) $offer->current;
			}
		}

		if ( null === $newest ) {
			/* translators: %s: WordPress version. */
			return [ Result::pass( self::ID, sprintf( __( 'WordPress %s is up to date.', 'sitewarden' ), $installed ) ) ];
		}

		// A newer release in the same branch (6.8.1 → 6.8.3) usually carries security fixes.
		$same_branch = self::branch( $newest ) === self::branch( $installed );
		return [
			Result::fail(
				self::ID,
				$same_branch ? Result::SEVERITY_HIGH : Result::SEVERITY_MEDIUM,
				/* translators: 1: installed version, 2: available version. */
				sprintf( __( 'WordPress %1$s is out of date: %2$s is available', 'sitewarden' ), $installed, $newest ),
				__( 'Back up the site, then update WordPress in Dashboard > Updates.', 'sitewarden' ),
				[],
				$same_branch
					? __( 'Minor releases mostly fix bugs and security holes. Attackers look for sites that have not installed them.', 'sitewarden' )
					: __( 'Older major versions get security fixes only for a while. Staying current keeps the site covered.', 'sitewarden' )
			),
		];
	}

	/**
	 * Major branch of a version, e.g. "6.8" for "6.8.3".
	 */
	private static function branch( string $version ): string {
		return implode( '.', array_slice( explode( '.', $version ), 0, 2 ) );
	}
}

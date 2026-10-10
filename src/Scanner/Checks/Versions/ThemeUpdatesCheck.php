<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Themes;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Theme updates, from the update check WordPress already runs (no requests of its own).
 */
class ThemeUpdatesCheck implements Check {
	public const ID = 'versions.themes';

	/**
	 * @var Transient
	 */
	private $transients;

	/**
	 * @var Themes
	 */
	private $themes;

	public function __construct() {
		$this->transients = ServiceFactory::create_transient();
		$this->themes     = ServiceFactory::create_themes();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Theme updates', 'hardenly' );
	}

	public function group(): string {
		return Group::VERSIONS;
	}

	public function run( Context $context ): array {
		$updates = $this->transients->get_site_transient( 'update_themes' );
		if ( ! is_object( $updates ) ) {
			return [ Result::skipped( self::ID, __( 'WordPress has not checked for theme updates yet.', 'hardenly' ) ) ];
		}

		$installed = (array) $this->themes->wp_get_themes();
		$outdated  = [];
		foreach ( isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : [] as $stylesheet => $update ) {
			if ( ! isset( $installed[ $stylesheet ], $update['new_version'] ) ) {
				continue;
			}
			$theme      = $installed[ $stylesheet ];
			$outdated[] = sprintf( '%s %s → %s', $theme->get( 'Name' ), $theme->get( 'Version' ), $update['new_version'] );
		}

		if ( [] === $outdated ) {
			return [ Result::pass( self::ID, __( 'All themes are up to date.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of themes. */
				sprintf( _n( '%d theme is out of date', '%d themes are out of date', count( $outdated ), 'hardenly' ), count( $outdated ) ),
				__( 'Update them in Appearance > Themes. Inactive themes can be attacked too: update or delete them.', 'hardenly' ),
				$outdated
			),
		];
	}
}

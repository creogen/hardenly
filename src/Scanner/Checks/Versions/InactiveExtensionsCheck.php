<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Themes;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\AdminIncludes;

defined( 'ABSPATH' ) || exit;

/**
 * Plugins and themes that are installed but not used.
 */
class InactiveExtensionsCheck implements Check {
	public const ID = 'versions.inactive';

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * @var Themes
	 */
	private $themes;

	public function __construct() {
		$this->plugins = ServiceFactory::create_plugins();
		$this->themes  = ServiceFactory::create_themes();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Unused plugins and themes', 'hardenly' );
	}

	public function group(): string {
		return Group::VERSIONS;
	}

	public function run( Context $context ): array {
		$unused = array_merge( $this->inactive_plugins(), $this->inactive_themes() );

		if ( [] === $unused ) {
			return [ Result::pass( self::ID, __( 'No unused plugins or themes are installed.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				/* translators: %d: number of plugins and themes. */
				sprintf( _n( '%d unused plugin or theme is installed', '%d unused plugins and themes are installed', count( $unused ), 'hardenly' ), count( $unused ) ),
				__( 'Delete what you do not need. Keep one default theme (such as the newest Twenty-something theme) to fall back on.', 'hardenly' ),
				$unused,
				__( 'Inactive plugins and themes can still be attacked through their files, and they are easy to forget to update.', 'hardenly' )
			),
		];
	}

	/**
	 * @return string[]
	 */
	private function inactive_plugins(): array {
		AdminIncludes::plugins();
		$inactive = [];
		foreach ( (array) $this->plugins->get_plugins() as $file => $data ) {
			if ( ! $this->plugins->is_plugin_active( (string) $file ) ) {
				/* translators: %s: plugin name. */
				$inactive[] = sprintf( __( 'Plugin: %s', 'hardenly' ), $data['Name'] ?? $file );
			}
		}
		return $inactive;
	}

	/**
	 * Inactive themes, leaving out the active theme, its parent and the newest default theme.
	 *
	 * @return string[]
	 */
	private function inactive_themes(): array {
		$used     = [ (string) $this->themes->get_stylesheet(), (string) $this->themes->get_template() ];
		$themes   = (array) $this->themes->wp_get_themes();
		$defaults = array_filter(
			array_keys( $themes ),
			static function ( $slug ): bool {
				return 1 === preg_match( '/^twenty[a-z-]+$/', (string) $slug );
			}
		);
		// Default themes are named by year in words; their release order follows the version of WordPress they shipped with.
		usort(
			$defaults,
			static function ( $a, $b ) use ( $themes ): int {
				return version_compare( (string) $themes[ $b ]->get( 'RequiresWP' ), (string) $themes[ $a ]->get( 'RequiresWP' ) );
			}
		);
		if ( [] !== $defaults ) {
			$used[] = (string) $defaults[0];
		}

		$inactive = [];
		foreach ( $themes as $slug => $theme ) {
			if ( ! in_array( (string) $slug, $used, true ) ) {
				/* translators: %s: theme name. */
				$inactive[] = sprintf( __( 'Theme: %s', 'hardenly' ), $theme->get( 'Name' ) );
			}
		}
		return $inactive;
	}
}

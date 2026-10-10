<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\AdminIncludes;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin updates, from the update check WordPress already runs (no requests of its own).
 */
class PluginUpdatesCheck implements Check {
	public const ID = 'versions.plugins';

	/**
	 * @var Transient
	 */
	private $transients;

	/**
	 * @var Plugins
	 */
	private $plugins;

	public function __construct() {
		$this->transients = ServiceFactory::create_transient();
		$this->plugins    = ServiceFactory::create_plugins();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Plugin updates', 'hardenly' );
	}

	public function group(): string {
		return Group::VERSIONS;
	}

	public function run( Context $context ): array {
		$updates = $this->transients->get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) ) {
			return [ Result::skipped( self::ID, __( 'WordPress has not checked for plugin updates yet.', 'hardenly' ) ) ];
		}

		AdminIncludes::plugins();
		$installed = (array) $this->plugins->get_plugins();
		$outdated  = [];
		foreach ( isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : [] as $file => $update ) {
			if ( ! isset( $installed[ $file ] ) || ! is_object( $update ) || ! isset( $update->new_version ) ) {
				continue;
			}
			$outdated[] = sprintf( '%s %s → %s', $installed[ $file ]['Name'] ?? $file, $installed[ $file ]['Version'] ?? '?', $update->new_version );
		}

		if ( [] === $outdated ) {
			return [ Result::pass( self::ID, __( 'All plugins are up to date.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of plugins. */
				sprintf( _n( '%d plugin is out of date', '%d plugins are out of date', count( $outdated ), 'hardenly' ), count( $outdated ) ),
				__( 'Update them on the Plugins screen. Most attacks target security holes that updates have already closed.', 'hardenly' ),
				$outdated
			),
		];
	}
}

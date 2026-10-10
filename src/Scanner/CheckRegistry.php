<?php declare( strict_types=1 );

namespace Sitewarden\Scanner;

use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The checks a scan runs: the built-in ones plus those added through `sitewarden_checks`.
 */
class CheckRegistry {
	private const ID_PATTERN = '/^[a-z0-9_.-]{1,100}$/';

	/**
	 * @var Check[]
	 */
	private $default_checks;

	/**
	 * @var array<string, Check>|null
	 */
	private $checks = null;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @param Check[] $default_checks Built-in checks.
	 */
	public function __construct( array $default_checks ) {
		$this->default_checks = $default_checks;
		$this->hooks          = ServiceFactory::create_hooks();
	}

	/**
	 * All valid checks by id, in run order (by group, then as registered).
	 *
	 * Entries that are not a Check or have an invalid or duplicate id are dropped, so a
	 * broken extension cannot break a scan.
	 *
	 * @return array<string, Check>
	 */
	public function all(): array {
		if ( null !== $this->checks ) {
			return $this->checks;
		}

		/**
		 * Filters the checks a scan runs.
		 *
		 * @param Check[] $checks Built-in checks. Add, remove or replace entries; each must implement
		 *                        Sitewarden\Scanner\Check and have a unique id.
		 */
		$filtered = $this->hooks->apply_filters( 'sitewarden_checks', $this->default_checks );

		$checks = [];
		foreach ( is_array( $filtered ) ? $filtered : $this->default_checks as $check ) {
			if ( ! $check instanceof Check ) {
				continue;
			}
			$id = $check->id();
			if ( 1 !== preg_match( self::ID_PATTERN, $id ) || isset( $checks[ $id ] ) ) {
				continue;
			}
			$checks[ $id ] = $check;
		}

		$order = array_flip( array_keys( $checks ) );
		uasort(
			$checks,
			static function ( Check $a, Check $b ) use ( $order ): int {
				return [ Group::position( $a->group() ), $order[ $a->id() ] ]
					<=> [ Group::position( $b->group() ), $order[ $b->id() ] ];
			}
		);

		$this->checks = $checks;
		return $checks;
	}

	/**
	 * Ids of the checks to run, in order, leaving out disabled groups.
	 *
	 * @param string[] $disabled_groups Group ids turned off in the settings.
	 *
	 * @return string[]
	 */
	public function enabled_ids( array $disabled_groups ): array {
		$ids = [];
		foreach ( $this->all() as $id => $check ) {
			if ( ! in_array( $check->group(), $disabled_groups, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	public function get( string $id ): ?Check {
		return $this->all()[ $id ] ?? null;
	}
}

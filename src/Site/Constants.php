<?php declare( strict_types=1 );

namespace Hardenly\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Reads PHP constants such as those set in wp-config.php, so checks can be tested.
 */
class Constants {
	public function is_defined( string $name ): bool {
		return defined( $name );
	}

	/**
	 * @param string $name          Constant name.
	 * @param mixed  $default_value Returned when the constant is not defined.
	 *
	 * @return mixed
	 */
	public function get( string $name, $default_value = null ) {
		return defined( $name ) ? constant( $name ) : $default_value;
	}

	/**
	 * Whether the constant is defined and truthy.
	 */
	public function is_true( string $name ): bool {
		return (bool) $this->get( $name, false );
	}
}

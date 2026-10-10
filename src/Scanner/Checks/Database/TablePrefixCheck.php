<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Database;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * The default `wp_` database table prefix.
 */
class TablePrefixCheck implements Check {
	public const ID = 'database.table_prefix';

	/**
	 * @var string|null
	 */
	private $prefix;

	/**
	 * @param string|null $prefix Table prefix; $wpdb->base_prefix by default.
	 */
	public function __construct( ?string $prefix = null ) {
		$this->prefix = $prefix;
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Database table prefix', 'hardenly' );
	}

	public function group(): string {
		return Group::DATABASE;
	}

	public function run( Context $context ): array {
		global $wpdb;
		$prefix = $this->prefix ?? (string) $wpdb->base_prefix;

		if ( 'wp_' !== $prefix ) {
			return [ Result::pass( self::ID, __( 'Database tables use a custom prefix.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				__( 'Database tables use the default "wp_" prefix', 'hardenly' ),
				__( 'Use a custom prefix ($table_prefix in wp-config.php) on new sites. Changing it on an existing site means renaming tables and some data, so do it only with a backup and a guide, or skip it: on its own the default prefix is a minor risk.', 'hardenly' ),
				[],
				__( 'Automated SQL injection attacks often assume the default table names.', 'hardenly' )
			),
		];
	}
}

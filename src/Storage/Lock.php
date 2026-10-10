<?php declare( strict_types=1 );

namespace Hardenly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * A lock shared by all requests, so two batches of a scan never run at the same time.
 *
 * Uses the same approach as WP_Upgrader::create_lock(): an INSERT IGNORE into the options
 * table is atomic, while add_option() and transients are not. The lock expires on its own,
 * so a request that dies while holding it does not block scans forever.
 */
class Lock {
	/**
	 * @var string
	 */
	private $option;

	/**
	 * @param string $option Option name that holds the lock and its expiry time.
	 */
	public function __construct( string $option ) {
		$this->option = $option;
	}

	/**
	 * Takes the lock if it is free or expired.
	 *
	 * @param int $ttl Seconds until the lock expires.
	 */
	public function acquire( int $ttl ): bool {
		global $wpdb;

		$expires = (string) ( time() + $ttl );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; the options API has no compare-and-set.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				$this->option,
				$expires
			)
		);
		if ( $inserted ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock written above.
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->option )
		);
		if ( null === $current || (int) $current > time() ) {
			return false;
		}

		// Expired: take it over. Only one request can replace the old value.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; the options API has no compare-and-set.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$expires,
				$this->option,
				$current
			)
		);
		return 1 === $updated;
	}

	public function release(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock row, never cached.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $this->option ) );
	}
}

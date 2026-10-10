<?php
/**
 * Smoke test for a release build: php tests/smoke/dist.php <build-dir>
 *
 * Loads the built plugin (scoped, with unused WPAL services pruned) with stand-ins for the
 * WordPress functions it calls, then runs its hooks. A WPAL service missing from the build
 * or a scoping problem fails here with a fatal error.
 *
 * When the plugin calls a new WordPress function, add a stand-in below, and run the new
 * code path from the bottom of this file.
 *
 * @package Sitewarden
 */

declare( strict_types=1 );

$build_dir = rtrim( $argv[1] ?? '', '/' );
$main_file = $build_dir . '/sitewarden.php';
if ( ! is_file( $main_file ) ) {
	fwrite( STDERR, "Usage: php tests/smoke/dist.php <build-dir>\n" );
	exit( 1 );
}
if ( is_dir( $build_dir . '/vendor' ) ) {
	fwrite( STDERR, "The build still has vendor/: run make dist.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['smoke_actions']  = [];
$GLOBALS['smoke_enqueued'] = [];
$GLOBALS['smoke_options']  = [];
$GLOBALS['smoke_routes']   = [];
$GLOBALS['smoke_events']   = [];

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['smoke_actions'][ $hook ][] = $callback;
	return true;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return add_action( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param mixed ...$args Arguments for the callbacks.
 */
function do_action( string $hook, ...$args ): void {
	foreach ( $GLOBALS['smoke_actions'][ $hook ] ?? [] as $callback ) {
		$callback( ...$args );
	}
}

/**
 * @param mixed $value Value to filter.
 * @param mixed ...$args Other arguments.
 *
 * @return mixed
 */
function apply_filters( string $hook, $value, ...$args ) {
	return $value;
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

/**
 * @param mixed $default_value Returned when the option is missing.
 *
 * @return mixed
 */
function get_option( string $option, $default_value = false ) {
	return $GLOBALS['smoke_options'][ $option ] ?? $default_value;
}

/**
 * @param mixed     $value    Value.
 * @param bool|null $autoload Autoload.
 */
function update_option( string $option, $value, $autoload = null ): bool {
	$GLOBALS['smoke_options'][ $option ] = $value;
	return true;
}

function delete_option( string $option ): bool {
	unset( $GLOBALS['smoke_options'][ $option ] );
	return true;
}

/**
 * @param mixed[] $args Event arguments.
 */
function wp_schedule_single_event( int $timestamp, string $hook, array $args = [], bool $wp_error = false ): bool {
	$GLOBALS['smoke_events'][] = [ $hook, $args ];
	return true;
}

/**
 * @param mixed[] $args Event arguments.
 */
function wp_clear_scheduled_hook( string $hook, array $args = [], bool $wp_error = false ): int {
	return 0;
}

function spawn_cron( int $gmt_time = 0 ): bool {
	return true;
}

function wp_convert_hr_to_bytes( string $value ): int {
	return 256 * 1024 * 1024;
}

/**
 * @param mixed ...$args Object id and the like.
 */
function current_user_can( string $capability, ...$args ): bool {
	return true;
}

/**
 * @param mixed[] $args Route options.
 */
function register_rest_route( string $route_namespace, string $route, array $args = [], bool $override = false ): bool {
	$GLOBALS['smoke_routes'][] = $route_namespace . $route;
	return true;
}

function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback, string $icon_url = '', ?int $position = null ): string {
	return 'toplevel_page_' . $menu_slug;
}

function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback, ?int $position = null ): string {
	return 'toplevel_page_' . $menu_slug;
}

// Stand-in for $wpdb, enough for the scan lock (Storage\Lock): every lock is free.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress is not loaded here.
$GLOBALS['wpdb'] = new class() {
	/**
	 * @var string
	 */
	public $options = 'wp_options';

	/**
	 * @param mixed ...$args Values.
	 */
	public function prepare( string $query, ...$args ): string {
		return $query;
	}

	public function query( string $query ): int {
		return 1;
	}

	/**
	 * No stored lock to read: query() always succeeds.
	 *
	 * @return null
	 */
	public function get_var( string $query ) {
		return null;
	}
};

function plugin_dir_url( string $file ): string {
	return 'https://example.com/wp-content/plugins/sitewarden/';
}

function wp_enqueue_style( string $handle, string $src = '' ): void {
	$GLOBALS['smoke_enqueued'][] = $src;
}

/**
 * @param mixed $value Data value.
 */
function wp_style_add_data( string $handle, string $key, $value ): bool {
	return true;
}

function wp_enqueue_script( string $handle, string $src = '' ): void {
	$GLOBALS['smoke_enqueued'][] = $src;
}

function wp_set_script_translations( string $handle, string $domain = 'default', string $path = '' ): bool {
	return true;
}

require $main_file;

do_action( 'init' );
do_action( 'admin_menu' );
do_action( 'rest_api_init' );
do_action( 'admin_enqueue_scripts', 'index.php' );
$failures = [];
if ( [] !== $GLOBALS['smoke_enqueued'] ) {
	$failures[] = 'Assets were enqueued on a screen that is not the plugin\'s.';
}
do_action( 'admin_enqueue_scripts', 'toplevel_page_sitewarden' );

foreach ( $GLOBALS['smoke_routes'] as $route ) {
	if ( 0 !== strpos( $route, 'sitewarden/v1/' ) ) {
		$failures[] = "Unexpected REST route $route.";
	}
}
if ( count( $GLOBALS['smoke_routes'] ) < 3 ) {
	$failures[] = 'REST routes are missing.';
}

// A whole scan: start it, then run the scheduled batch.
$sitewarden_rest = $GLOBALS['smoke_actions']['rest_api_init'][0][0];
$sitewarden_rest->start();
foreach ( $GLOBALS['smoke_events'] as $event ) {
	do_action( $event[0], ...$event[1] );
}
$sitewarden_report = $sitewarden_rest->get_report();
if ( ! isset( $sitewarden_report['report']['score'] ) ) {
	$failures[] = 'The scan did not store a report.';
}

foreach ( $GLOBALS['smoke_enqueued'] as $url ) {
	$file = $build_dir . '/' . substr( $url, strlen( plugin_dir_url( $main_file ) ) );
	if ( ! is_file( $file ) ) {
		$failures[] = "Enqueued $url, but the build has no $file.";
	}
}
if ( [] === $GLOBALS['smoke_enqueued'] ) {
	$failures[] = 'Nothing was enqueued.';
}

if ( [] !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo 'Smoke test passed: ' . count( $GLOBALS['smoke_enqueued'] ) . " assets enqueued.\n";

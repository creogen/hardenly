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
do_action( 'admin_enqueue_scripts', 'index.php' );

$failures = [];
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

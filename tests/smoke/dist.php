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
 * @package Hardenly
 */

declare( strict_types=1 );

$build_dir = rtrim( $argv[1] ?? '', '/' );
$main_file = $build_dir . '/hardenly.php';
if ( ! is_file( $main_file ) ) {
	fwrite( STDERR, "Usage: php tests/smoke/dist.php <build-dir>\n" );
	exit( 1 );
}
if ( is_dir( $build_dir . '/vendor' ) ) {
	fwrite( STDERR, "The build still has vendor/: run make dist.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../stubs/WP_Error.php';
require __DIR__ . '/../stubs/WP_REST_Request.php';
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['smoke_actions']  = [];
$GLOBALS['smoke_enqueued'] = [];
$GLOBALS['smoke_options']  = [];
$GLOBALS['smoke_routes']   = [];
$GLOBALS['smoke_pages']    = [];
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
	$GLOBALS['smoke_pages'][ $menu_slug ] = $callback;
	return 'toplevel_page_' . $menu_slug;
}

define( 'DAY_IN_SECONDS', 86400 );

function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

/**
 * @param mixed $value Value.
 * @param mixed $autoload Autoload.
 */
function add_option( string $option, $value = '', string $deprecated = '', $autoload = null ): bool {
	return update_option( $option, $value );
}

/**
 * @return array<string, string>
 */
function wp_load_alloptions( bool $force_cache = false ): array {
	return [ 'blogname' => 'Smoke' ];
}

/**
 * @return mixed
 */
function get_site_transient( string $transient ) {
	return false;
}

function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
	return '6.8.3';
}

function wp_get_environment_type(): string {
	return 'production';
}

/**
 * @param int|string $value Value.
 *
 * @return false
 */
function get_user_by( string $field, $value ) {
	return false;
}

/**
 * @return array<string, mixed>
 */
function count_users( string $strategy = 'time', ?int $site_id = null ): array {
	return [ 'avail_roles' => [ 'administrator' => 1 ] ];
}

/**
 * @param array<string, mixed> $args Query.
 *
 * @return object[]
 */
function get_users( array $args = [] ): array {
	return [];
}

/**
 * @return mixed
 */
function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
	return '';
}

/**
 * @return array<string, array<string, string>>
 */
function get_plugins( string $plugin_folder = '' ): array {
	return [ 'hardenly/hardenly.php' => [ 'Name' => 'Hardenly' ] ];
}

function is_plugin_active( string $plugin ): bool {
	return true;
}

/**
 * @param array<string, mixed> $args Arguments.
 *
 * @return object[]
 */
function wp_get_themes( array $args = [] ): array {
	return [];
}

function get_stylesheet(): string {
	return 'twentytwentyfive';
}

function get_template(): string {
	return 'twentytwentyfive';
}

function home_url( string $path = '', ?string $scheme = null ): string {
	return 'https://example.com' . $path;
}

function site_url( string $path = '', ?string $scheme = null ): string {
	return 'https://example.com/' . ltrim( $path, '/' );
}

function content_url( string $path = '' ): string {
	return 'https://example.com/wp-content/' . $path;
}

function rest_url( string $path = '', string $scheme = 'rest' ): string {
	return 'https://example.com/wp-json/' . $path;
}

/**
 * @return mixed
 */
function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Stand-in for it.
}

/**
 * @return array<string, mixed>
 */
function wp_upload_dir( ?string $time = null, bool $create_dir = true, bool $refresh_cache = false ): array {
	return [
		'basedir' => __DIR__ . '/missing-uploads',
		'baseurl' => 'https://example.com/wp-content/uploads',
		'error'   => false,
	];
}

/**
 * Every request fails: the smoke test has no web server.
 *
 * @param array<string, mixed> $args Arguments.
 */
function wp_remote_get( string $url, array $args = [] ): WP_Error {
	return new WP_Error();
}

/**
 * @param array<string, mixed> $args Arguments.
 */
function wp_remote_post( string $url, array $args = [] ): WP_Error {
	return new WP_Error();
}

/**
 * @param mixed $thing Value.
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

function register_activation_hook( string $file, callable $callback ): void {
	$GLOBALS['smoke_activate'] = $callback;
}

function register_deactivation_hook( string $file, callable $callback ): void {
	$GLOBALS['smoke_deactivate'] = $callback;
}

/**
 * @param array<string, mixed> $args Arguments.
 */
function register_setting( string $option_group, string $option_name, array $args = [] ): void {
	$GLOBALS['smoke_setting'] = $args;
}

function settings_errors( string $setting = '', bool $sanitize = false, bool $hide_on_update = false ): void {
}

/**
 * @param int|string $action Action.
 */
function wp_nonce_field( $action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
	echo '<input type="hidden" name="_wpnonce" value="nonce">';
	return '';
}

function wp_date( string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null ): string {
	return gmdate( $format, $timestamp ?? time() );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}

function esc_textarea( string $text ): string {
	return esc_html( $text );
}

/**
 * @param mixed[] $args Event arguments.
 *
 * @return false
 */
function wp_get_schedule( string $hook, array $args = [] ) {
	return false;
}

/**
 * @param mixed[] $args Event arguments.
 *
 * @return false
 */
function wp_next_scheduled( string $hook, array $args = [] ) {
	return false;
}

function smoke_request( string $key, string $value ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST' );
	$request->set_param( $key, $value );
	return $request;
}

/**
 * @param mixed[] $args Event arguments.
 */
function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false ): bool {
	return true;
}

function wp_unschedule_hook( string $hook, bool $wp_error = false ): int {
	$GLOBALS['smoke_unscheduled'][] = $hook;
	return 0;
}

function wp_get_current_user(): object {
	return (object) [
		'ID'         => 1,
		'user_login' => 'admin',
	];
}

function sanitize_key( string $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function sanitize_text_field( string $text ): string {
	return trim( strip_tags( $text ) );
}

function sanitize_user( string $username, bool $strict = false ): string {
	return $username;
}

function sanitize_email( string $email ): string {
	return trim( $email );
}

/**
 * @return string|false
 */
function is_email( string $email, bool $deprecated = false ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

/**
 * @param mixed $data Data.
 */
function smoke_json( $data ): string {
	return (string) json_encode( $data );
}

function admin_url( string $path = '', string $scheme = 'admin' ): string {
	return 'https://example.com/wp-admin/' . $path;
}

function get_current_user_id(): int {
	return 1;
}

function esc_url( string $url ): string {
	return htmlspecialchars( $url, ENT_QUOTES );
}

/**
 * @return mixed
 */
function get_transient( string $transient ) {
	return false;
}

/**
 * @param mixed $value Value.
 */
function set_transient( string $transient, $value, int $expiration = 0 ): bool {
	return true;
}

function delete_transient( string $transient ): bool {
	$GLOBALS['smoke_deleted_transients'][] = $transient;
	return true;
}

function get_locale(): string {
	return 'en_US';
}

/**
 * WordPress.org cannot be reached here.
 *
 * @return false
 */
function get_core_checksums( string $version, string $locale ) {
	return false;
}

/**
 * Every outbound request fails: the smoke test has no network.
 *
 * @param array<string, mixed> $args Arguments.
 */
function wp_safe_remote_get( string $url, array $args = [] ): WP_Error {
	return new WP_Error();
}

// Stand-in for $wpdb, enough for the scan lock (Storage\Lock): every lock is free.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress is not loaded here.
$GLOBALS['wpdb'] = new class() {
	/**
	 * @var string
	 */
	public $options = 'wp_options';

	/**
	 * @var string
	 */
	public $base_prefix = 'wp_';

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
	return 'https://example.com/wp-content/plugins/hardenly/';
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
do_action( 'admin_enqueue_scripts', 'toplevel_page_hardenly' );

foreach ( $GLOBALS['smoke_routes'] as $route ) {
	if ( 0 !== strpos( $route, 'hardenly/v1/' ) ) {
		$failures[] = "Unexpected REST route $route.";
	}
}
if ( count( $GLOBALS['smoke_routes'] ) < 3 ) {
	$failures[] = 'REST routes are missing.';
}

// A whole scan: start it, then run the scheduled batch.
$hardenly_rest = $GLOBALS['smoke_actions']['rest_api_init'][0][0];
// Turn on the online services, so their code runs in the build (requests fail here).
update_option(
	'hardenly_settings',
	[
		'checksums_enabled' => true,
		'vuln_provider'     => 'wpscan',
		'vuln_api_key'      => 'smoke-key',
		'vuln_consent'      => true,
	]
);
$hardenly_rest->start();
foreach ( $GLOBALS['smoke_events'] as $event ) {
	do_action( $event[0], ...$event[1] );
}
$hardenly_report = $hardenly_rest->get_report();
if ( ! isset( $hardenly_report['report']['score'] ) ) {
	$failures[] = 'The scan did not store a report.';
} elseif ( count( $hardenly_report['report']['checks'] ) < 33 ) {
	$failures[] = 'The scan ran ' . count( $hardenly_report['report']['checks'] ) . ' checks, expected all built-in ones.';
} else {
	foreach ( $hardenly_report['report']['results'] as $hardenly_result ) {
		// A check that throws is reported as skipped with this message: in the build, it means a missing class or function.
		if ( false !== strpos( $hardenly_result['message'], 'could not run' ) ) {
			$failures[] = $hardenly_result['check_id'] . ': ' . $hardenly_result['message'];
		}
	}
}

$hardenly_online = [];
foreach ( $hardenly_report['report']['results'] ?? [] as $hardenly_result ) {
	if ( 0 === strpos( $hardenly_result['check_id'], 'online.' ) ) {
		$hardenly_online[ $hardenly_result['check_id'] ] = $hardenly_result['status'];
	}
}
if ( 'skipped' !== ( $hardenly_online['online.vulnerabilities'] ?? '' ) || 'skipped' !== ( $hardenly_online['online.core_checksums'] ?? '' ) ) {
	$failures[] = 'The online checks did not run as expected: ' . smoke_json( $hardenly_online );
}

// Ignore and show again a problem the scan found, and skip a file.
$hardenly_findings = $GLOBALS['smoke_actions']['rest_api_init'][1][0];
$hardenly_problem  = null;
foreach ( $hardenly_report['report']['results'] ?? [] as $hardenly_result ) {
	if ( 'fail' === $hardenly_result['status'] ) {
		$hardenly_problem = $hardenly_result['id'];
		break;
	}
}
if ( null === $hardenly_problem ) {
	$failures[] = 'The scan found no problem to ignore.';
} else {
	$hardenly_ignored = $hardenly_findings->ignore( smoke_request( 'id', $hardenly_problem ) );
	if ( ! is_array( $hardenly_ignored ) || 1 !== $hardenly_ignored['report']['ignored'] ) {
		$failures[] = 'Ignoring a finding did not work.';
	}
	$hardenly_findings->restore( smoke_request( 'id', $hardenly_problem ) );
}
$hardenly_skipped = $hardenly_findings->skip_file( smoke_request( 'path', 'wp-content/uploads/x.php' ) );
if ( ! is_array( $hardenly_skipped ) || [ 'wp-content/uploads/x.php' ] !== $hardenly_skipped['skip_files'] ) {
	$failures[] = 'Skipping a file did not work.';
}

// The settings screen.
do_action( 'admin_init' );
if ( ! isset( $GLOBALS['smoke_setting']['sanitize_callback'] ) ) {
	$failures[] = 'The settings are not registered.';
}
ob_start();
( $GLOBALS['smoke_pages']['hardenly-settings'] )();
$hardenly_settings_html = (string) ob_get_clean();
if ( false === strpos( $hardenly_settings_html, 'action="options.php"' ) ) {
	$failures[] = 'The settings screen did not render.';
}
( $GLOBALS['smoke_activate'] )( false );
( $GLOBALS['smoke_deactivate'] )();

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

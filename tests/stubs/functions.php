<?php
/**
 * Stand-ins for the WordPress functions and constants the plugin uses directly (translation,
 * escaping), so unit tests run without WordPress. Everything else goes through WPAL mocks.
 *
 * @package Hardenly
 */

declare( strict_types=1 );

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Lets AdminIncludes::plugins() see the admin plugin functions as loaded.
 *
 * @return array<string, array<string, string>>
 */
function get_plugins(): array {
	return [];
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_textarea( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_url( string $url ): string {
	return htmlspecialchars( $url, ENT_QUOTES );
}

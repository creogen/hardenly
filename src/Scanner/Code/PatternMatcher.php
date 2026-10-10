<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Code;

use Hardenly\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Finds code patterns typical of hidden or injected code: code that decodes and runs itself,
 * commands taken from request data, long encoded blobs, and names spelled with escapes.
 *
 * These are heuristics. Legitimate code can match, so findings are "suspicious", never proof.
 * Comments are ignored, so documentation that mentions a pattern does not match.
 */
class PatternMatcher {
	/**
	 * Request data an attacker controls.
	 */
	private const REQUEST = '\$_(?:GET|POST|REQUEST|COOKIE)\b';

	/**
	 * Rules by id: a regular expression and the severity of a match.
	 */
	public const RULES = [
		// eval( base64_decode( ... ) ) and similar: runs code that was hidden on purpose.
		'eval_decode'      => [
			'pattern'  => '~\b(?:eval|assert)\s*\(\s*@?\s*(?:base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|strrev|hex2bin|convert_uudecode|rawurldecode)\s*\(~i',
			'severity' => Result::SEVERITY_HIGH,
		],
		// eval( $_POST['x'] ): runs code sent by a visitor.
		'eval_request'     => [
			'pattern'  => '~\b(?:eval|assert|create_function)\s*\([^;]{0,200}?' . self::REQUEST . '~i',
			'severity' => Result::SEVERITY_HIGH,
		],
		// system( $_GET['cmd'] ): runs server commands sent by a visitor. Not ->exec() (PDO).
		'exec_request'     => [
			'pattern'  => '~(?<![\w$>:])(?:system|exec|shell_exec|passthru|popen|proc_open|pcntl_exec)\s*\([^;]{0,200}?' . self::REQUEST . '~i',
			'severity' => Result::SEVERITY_HIGH,
		],
		// $_GET['f']( ... ): calls a function named by a visitor.
		'request_function' => [
			'pattern'  => '~' . self::REQUEST . '\s*\[[^\]]{1,100}\]\s*\(~',
			'severity' => Result::SEVERITY_HIGH,
		],
		// include $_GET['page']: loads a file named by a visitor.
		'include_request'  => [
			'pattern'  => '~\b(?:include|include_once|require|require_once)\b[\s(]{0,5}[^;]{0,100}?' . self::REQUEST . '~i',
			'severity' => Result::SEVERITY_HIGH,
		],
		// preg_replace( '/x/e', ... ): the /e modifier evaluates the replacement as code (removed in PHP 7).
		'preg_replace_e'   => [
			'pattern'  => '~\bpreg_replace\s*\(\s*([\'"])([^\w\s\\\\])(?:(?!\1).){0,300}?\2[a-zA-Z]*e[a-zA-Z]*\1~',
			'severity' => Result::SEVERITY_MEDIUM,
		],
		// A long base64 string in a file that also decodes or runs code.
		'encoded_blob'     => [
			'pattern'  => '~([\'"])[A-Za-z0-9+/]{2000,}={0,2}\1~',
			'severity' => Result::SEVERITY_MEDIUM,
		],
		// "\x65\x76\x61\x6c": names and code spelled with escapes so they cannot be searched for.
		'hex_escapes'      => [
			'pattern'  => '~(?:\\\\x[0-9a-fA-F]{2}){12,}~',
			'severity' => Result::SEVERITY_MEDIUM,
		],
		// Text spelled with a chain of chr() calls, so it cannot be searched for.
		'chr_chain'        => [
			'pattern'  => '~(?:\bchr\s*\(\s*\d{1,3}\s*\)\s*\.\s*){8,}~i',
			'severity' => Result::SEVERITY_MEDIUM,
		],
	];

	/**
	 * Translated description of a rule, for reports.
	 */
	public static function label( string $rule ): string {
		$labels = [
			'eval_decode'      => __( 'decodes and runs hidden code', 'hardenly' ),
			'eval_request'     => __( 'runs code sent by visitors', 'hardenly' ),
			'exec_request'     => __( 'runs server commands sent by visitors', 'hardenly' ),
			'request_function' => __( 'calls a function named by visitors', 'hardenly' ),
			'include_request'  => __( 'loads a file named by visitors', 'hardenly' ),
			'preg_replace_e'   => __( 'runs code through preg_replace /e', 'hardenly' ),
			'encoded_blob'     => __( 'long encoded data next to code that decodes it', 'hardenly' ),
			'hex_escapes'      => __( 'text hidden in escape sequences', 'hardenly' ),
			'chr_chain'        => __( 'text hidden in chr() calls', 'hardenly' ),
		];
		return $labels[ $rule ] ?? $rule;
	}

	/**
	 * Matches in some PHP code, the first one per rule, in rule order.
	 *
	 * @param string $code File contents.
	 *
	 * @return array<int, array{rule: string, severity: string, line: int, excerpt: string}>
	 */
	public function find( string $code ): array {
		// Most files match nothing; only then is it worth removing comments and looking again.
		if ( [] === $this->match( $code, $code ) ) {
			return [];
		}
		return $this->match( self::without_comments( $code ), $code );
	}

	/**
	 * @param string $code     Code to search.
	 * @param string $original The same code with comments, at the same offsets, for excerpts.
	 *
	 * @return array<int, array{rule: string, severity: string, line: int, excerpt: string}>
	 */
	private function match( string $code, string $original ): array {
		$found = [];
		foreach ( self::RULES as $rule => $definition ) {
			$offset = $this->first_match( $rule, $definition['pattern'], $code );
			if ( null === $offset ) {
				continue;
			}
			$found[] = [
				'rule'     => $rule,
				'severity' => $definition['severity'],
				'line'     => substr_count( $original, "\n", 0, $offset ) + 1,
				'excerpt'  => self::excerpt( $original, $offset ),
			];
		}
		return $found;
	}

	/**
	 * Offset of the first match that also passes the rule's extra conditions, or null.
	 */
	private function first_match( string $rule, string $pattern, string $code ): ?int {
		if ( ! preg_match_all( $pattern, $code, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			// No match, or the regular expression gave up on this file.
			return null;
		}

		foreach ( $matches as $match ) {
			$text = $match[0][0];
			if ( 'encoded_blob' === $rule && 1 !== preg_match( '~\b(?:base64_decode|gzinflate|gzuncompress|eval)\s*\(~i', $code ) ) {
				return null;
			}
			if ( 'hex_escapes' === $rule && ! self::decodes_to_text( $text ) ) {
				continue;
			}
			return (int) $match[0][1];
		}
		return null;
	}

	/**
	 * Whether escaped bytes spell readable code rather than binary data (such as a Unicode table).
	 */
	private static function decodes_to_text( string $escapes ): bool {
		$decoded  = (string) preg_replace_callback(
			'~\\\\x([0-9a-fA-F]{2})~',
			static function ( array $hex ): string {
				return chr( (int) hexdec( $hex[1] ) );
			},
			$escapes
		);
		$readable = preg_match_all( '~[A-Za-z0-9_$()\[\];.,\'" =]~', $decoded );
		return strlen( $decoded ) > 0 && $readable / strlen( $decoded ) >= 0.9;
	}

	/**
	 * The code with comments blanked out. Newlines and other offsets stay where they were.
	 */
	private static function without_comments( string $code ): string {
		if ( ! function_exists( 'token_get_all' ) ) {
			return $code;
		}

		$result = '';
		foreach ( token_get_all( $code ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				$result .= (string) preg_replace( '~[^\n]~', ' ', $token[1] );
			} else {
				$result .= is_array( $token ) ? $token[1] : $token;
			}
		}
		return strlen( $result ) === strlen( $code ) ? $result : $code;
	}

	/**
	 * A short, single-line piece of the code at an offset.
	 */
	private static function excerpt( string $code, int $offset ): string {
		$text = (string) preg_replace( '~\s+~', ' ', substr( $code, $offset, 120 ) );
		$text = (string) preg_replace( '~[^\x20-\x7E]~', '?', $text );
		return strlen( $text ) > 60 ? substr( $text, 0, 57 ) . '...' : $text;
	}
}

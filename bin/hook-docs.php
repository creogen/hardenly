<?php
/**
 * Generates docs/developer/hooks.md from the docblocks above the plugin's hooks.
 *
 *     php bin/hook-docs.php          Writes the file.
 *     php bin/hook-docs.php --check  Fails when the file is out of date.
 *
 * Reads PHP tokens instead of matching function calls, so hooks fired through WPAL
 * (`$this->hooks->apply_filters( ... )`) are found too. Only hooks named with a literal
 * `hardenly_` string are documented; each needs a docblock directly above its statement.
 *
 * @package Hardenly
 */

declare( strict_types=1 );

const HARDENLY_HOOK_DOCS_SOURCE = 'https://github.com/creogen/hardenly/blob/main/';

const HARDENLY_HOOK_DOCS_FUNCTIONS = [
	'apply_filters'           => 'Filter',
	'apply_filters_ref_array' => 'Filter',
	'do_action'               => 'Action',
	'do_action_ref_array'     => 'Action',
];

$hardenly_root   = dirname( __DIR__ );
$hardenly_target = $hardenly_root . '/docs/developer/hooks.md';

$hardenly_hooks  = [];
$hardenly_errors = [];
foreach ( hardenly_hook_docs_files( $hardenly_root ) as $hardenly_file ) {
	foreach ( hardenly_hook_docs_find( (string) file_get_contents( $hardenly_root . '/' . $hardenly_file ) ) as $hardenly_hook ) {
		$hardenly_where = $hardenly_file . ':' . $hardenly_hook['line'];
		if ( null === $hardenly_hook['doc'] ) {
			$hardenly_errors[] = $hardenly_where . ': ' . $hardenly_hook['name'] . ' has no docblock.';
			continue;
		}
		if ( isset( $hardenly_hooks[ $hardenly_hook['name'] ] ) ) {
			$hardenly_hooks[ $hardenly_hook['name'] ]['files'][] = $hardenly_file;
			continue;
		}
		$hardenly_hooks[ $hardenly_hook['name'] ] = [
			'type'  => $hardenly_hook['type'],
			'doc'   => hardenly_hook_docs_parse( $hardenly_hook['doc'] ),
			'files' => [ $hardenly_file ],
		];
	}
}

if ( $hardenly_errors ) {
	fwrite( STDERR, implode( "\n", $hardenly_errors ) . "\n" );
	exit( 1 );
}

ksort( $hardenly_hooks );
$hardenly_markdown = hardenly_hook_docs_render( $hardenly_hooks );

if ( in_array( '--check', $_SERVER['argv'] ?? [], true ) ) {
	if ( ! is_file( $hardenly_target ) || file_get_contents( $hardenly_target ) !== $hardenly_markdown ) {
		fwrite( STDERR, "docs/developer/hooks.md is out of date, run 'make docs'.\n" );
		exit( 1 );
	}
	exit( 0 );
}

file_put_contents( $hardenly_target, $hardenly_markdown );
echo 'Documented ' . count( $hardenly_hooks ) . " hooks in docs/developer/hooks.md.\n";

/**
 * PHP files that can fire hooks, relative to the plugin root and sorted.
 *
 * @return string[]
 */
function hardenly_hook_docs_files( string $root ): array {
	$files    = [ 'hardenly.php' ];
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = substr( $file->getPathname(), strlen( $root ) + 1 );
		}
	}
	sort( $files );
	return $files;
}

/**
 * Hook calls in PHP code with a literal `hardenly_` name and the docblock above their statement.
 *
 * @return array<int, array{name: string, type: string, line: int, doc: string|null}>
 */
function hardenly_hook_docs_find( string $code ): array {
	$names = [ T_STRING ];
	if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
		$names[] = T_NAME_FULLY_QUALIFIED;
	}

	// Without whitespace and plain comments, so neighbours are the tokens that matter.
	$tokens = array_values(
		array_filter(
			token_get_all( $code ),
			static function ( $token ): bool {
				return ! is_array( $token ) || ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT ], true );
			}
		)
	);

	$hooks = [];
	$doc   = null;
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) ) {
			// A docblock belongs to the statement that follows it, no further.
			if ( in_array( $token, [ ';', '{', '}' ], true ) ) {
				$doc = null;
			}
			continue;
		}
		if ( T_DOC_COMMENT === $token[0] ) {
			$doc = $token[1];
			continue;
		}

		$function = strtolower( ltrim( $token[1], '\\' ) );
		if ( ! in_array( $token[0], $names, true ) || ! isset( HARDENLY_HOOK_DOCS_FUNCTIONS[ $function ] ) ) {
			continue;
		}
		$previous = $tokens[ $i - 1 ] ?? null;
		$open     = $tokens[ $i + 1 ] ?? null;
		$name     = $tokens[ $i + 2 ] ?? null;
		$after    = $tokens[ $i + 3 ] ?? null;
		if ( is_array( $previous ) && T_FUNCTION === $previous[0] ) {
			continue;
		}
		if ( '(' !== $open || ! is_array( $name ) || T_CONSTANT_ENCAPSED_STRING !== $name[0] || ! in_array( $after, [ ',', ')' ], true ) ) {
			continue;
		}
		$name = substr( $name[1], 1, -1 );
		if ( 0 !== strpos( $name, 'hardenly_' ) ) {
			continue;
		}

		$hooks[] = [
			'name' => $name,
			'type' => HARDENLY_HOOK_DOCS_FUNCTIONS[ $function ],
			'line' => $token[2],
			'doc'  => $doc,
		];
	}
	return $hooks;
}

/**
 * Splits a docblock into its description and tags.
 *
 * @return array{description: string, params: array<int, array{type: string, name: string, description: string}>, since: string}
 */
function hardenly_hook_docs_parse( string $doc ): array {
	$lines = preg_split( '/\R/', trim( substr( $doc, 3, -2 ) ) );
	$lines = array_map(
		static function ( string $line ): string {
			return (string) preg_replace( '/^\s*\*\s?/', '', rtrim( $line ) );
		},
		false === $lines ? [] : $lines
	);

	$description = [];
	$tags        = [];
	foreach ( $lines as $line ) {
		if ( 1 === preg_match( '/^@(\w+)\s*(.*)$/', $line, $match ) ) {
			$tags[] = [ $match[1], $match[2] ];
		} elseif ( $tags ) {
			// Continuation of the previous tag.
			$tags[ count( $tags ) - 1 ][1] .= ' ' . trim( $line );
		} else {
			$description[] = $line;
		}
	}

	$params = [];
	$since  = '';
	foreach ( $tags as list( $tag, $text ) ) {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		if ( 'param' === $tag && 1 === preg_match( '/^(\S+)\s+(\$\w+)\s*(.*)$/', $text, $match ) ) {
			$params[] = [
				'type'        => $match[1],
				'name'        => $match[2],
				'description' => $match[3],
			];
		} elseif ( 'since' === $tag ) {
			$since = $text;
		}
	}

	return [
		'description' => trim( implode( "\n", $description ) ),
		'params'      => $params,
		'since'       => $since,
	];
}

/**
 * The Markdown page.
 *
 * @param array<string, array{type: string, doc: array{description: string, params: array<int, array{type: string, name: string, description: string}>, since: string}, files: string[]}> $hooks Hooks by name.
 */
function hardenly_hook_docs_render( array $hooks ): string {
	$out   = [];
	$out[] = '# Hooks';
	$out[] = '';
	$out[] = '<!-- Generated by bin/hook-docs.php from the docblocks in src/. Do not edit; run `make docs`. -->';
	$out[] = '';
	$out[] = 'Actions and filters Hardenly fires. See [Extending Hardenly](extending.md) for examples.';
	$out[] = '';
	$out[] = '| Hook | Type | Summary |';
	$out[] = '| --- | --- | --- |';
	foreach ( $hooks as $name => $hook ) {
		$summary = strtok( $hook['doc']['description'], "\n" );
		$out[]   = '| [`' . $name . '`](#' . $name . ') | ' . $hook['type'] . ' | ' . hardenly_hook_docs_cell( false === $summary ? '' : $summary ) . ' |';
	}

	foreach ( $hooks as $name => $hook ) {
		$doc    = $hook['doc'];
		$params = array_map(
			static function ( array $param ): string {
				return $param['type'] . ' ' . $param['name'];
			},
			$doc['params']
		);
		$call   = 'Filter' === $hook['type'] ? 'apply_filters' : 'do_action';

		$out[] = '';
		// An explicit anchor: GitHub and Jekyll derive different ids from headings with `_`.
		$out[] = '<a id="' . $name . '"></a>';
		$out[] = '';
		$out[] = '## `' . $name . '`';
		$out[] = '';
		$out[] = $hook['type'] . ( '' !== $doc['since'] ? ', since ' . $doc['since'] : '' ) . '.';
		$out[] = '';
		if ( '' !== $doc['description'] ) {
			$out[] = $doc['description'];
			$out[] = '';
		}
		$out[] = '```php';
		$out[] = $call . "( '" . $name . "'" . ( $params ? ', ' . implode( ', ', $params ) : '' ) . ' )';
		$out[] = '```';
		if ( $doc['params'] ) {
			$out[] = '';
			$out[] = '| Parameter | Type | Description |';
			$out[] = '| --- | --- | --- |';
			foreach ( $doc['params'] as $param ) {
				$out[] = '| `' . $param['name'] . '` | `' . hardenly_hook_docs_cell( $param['type'] ) . '` | ' . hardenly_hook_docs_cell( $param['description'] ) . ' |';
			}
		}
		$out[] = '';
		$out[] = 'Source: ' . implode(
			', ',
			array_map(
				static function ( string $file ): string {
					return '[' . $file . '](' . HARDENLY_HOOK_DOCS_SOURCE . $file . ')';
				},
				$hook['files']
			)
		);
	}

	return implode( "\n", $out ) . "\n";
}

/**
 * Text safe inside a Markdown table cell.
 */
function hardenly_hook_docs_cell( string $text ): string {
	return str_replace( [ '|', "\n" ], [ '\|', ' ' ], $text );
}

<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Online;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Integrity\ChecksumComparer;
use Hardenly\Integrity\WordPressOrgChecksums;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\AdminIncludes;
use Hardenly\Site\Constants;
use Hardenly\Site\Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin files compared with the official checksums from WordPress.org (opt-in). Plugins
 * that are not hosted there cannot be checked and are listed as such.
 */
class PluginChecksumsCheck implements Check {
	public const ID = 'online.plugin_checksums';

	/**
	 * @var WordPressOrgChecksums
	 */
	private $source;

	/**
	 * @var ChecksumComparer
	 */
	private $comparer;

	/**
	 * @var Constants
	 */
	private $constants;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * @param WordPressOrgChecksums|null $source     Checksums.
	 * @param Filesystem|null            $filesystem File access.
	 * @param Constants|null             $constants  Constants (WP_PLUGIN_DIR).
	 */
	public function __construct( ?WordPressOrgChecksums $source = null, ?Filesystem $filesystem = null, ?Constants $constants = null ) {
		$this->source    = $source ?? new WordPressOrgChecksums();
		$this->comparer  = new ChecksumComparer( $filesystem );
		$this->constants = $constants ?? new Constants();
		$this->plugins   = ServiceFactory::create_plugins();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Plugin files', 'hardenly' );
	}

	public function group(): string {
		return Group::ONLINE;
	}

	public function run( Context $context ): array {
		if ( ! $context->setting( 'checksums_enabled', false ) ) {
			return [];
		}

		AdminIncludes::plugins();
		$plugins = (array) $this->plugins->get_plugins();
		ksort( $plugins );
		$files = array_keys( $plugins );
		$dir   = rtrim( (string) $this->constants->get( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' ), '/\\' );
		$state = $context->cursor() ?? [
			'index'     => 0,
			'progress'  => null,
			'found'     => ChecksumComparer::start(),
			'unchecked' => [],
		];

		$count = count( $files );
		while ( $state['index'] < $count ) {
			$file    = (string) $files[ $state['index'] ];
			$name    = (string) ( $plugins[ $file ]['Name'] ?? $file );
			$slug    = false === strpos( $file, '/' ) ? '' : dirname( $file );
			$sums    = '' === $slug ? null : $this->source->plugin( $slug, (string) ( $plugins[ $file ]['Version'] ?? '' ) );
			$current = $state['progress'] ?? ChecksumComparer::start();

			if ( null === $sums ) {
				$state['unchecked'][] = $name;
				$current['done']      = true;
			} else {
				$current = $this->comparer->step( $dir . '/' . $slug, $sums, [ '' ], $current, $context, $slug . '/' );
			}

			if ( ! $current['done'] ) {
				$state['progress'] = $current;
				$context->pause( $state );
				return [];
			}

			$state['found']    = self::merge( $state['found'], $current );
			$state['progress'] = null;
			++$state['index'];
			if ( $state['index'] < $count && $context->should_yield() ) {
				$context->pause( $state );
				return [];
			}
		}

		return self::results( $state['found'], $state['unchecked'] );
	}

	/**
	 * Adds one plugin's modified and added files to the totals. Missing files are left out:
	 * plugins work without their readme or language files.
	 *
	 * @param array<string, mixed> $found   Totals so far.
	 * @param array<string, mixed> $plugin  One plugin's comparison.
	 *
	 * @return array<string, mixed>
	 */
	private static function merge( array $found, array $plugin ): array {
		foreach ( [ 'modified', 'added' ] as $list ) {
			$found[ $list ]             = array_slice( array_merge( $found[ $list ], $plugin[ $list ] ), 0, ChecksumComparer::MAX_PATHS );
			$found[ $list . '_total' ] += $plugin[ $list . '_total' ];
		}
		return $found;
	}

	/**
	 * @param array<string, mixed> $found     Totals.
	 * @param string[]             $unchecked Names of plugins WordPress.org has no checksums for.
	 *
	 * @return Result[]
	 */
	private static function results( array $found, array $unchecked ): array {
		$results = [];
		$total   = $found['modified_total'] + $found['added_total'];
		if ( $total > 0 ) {
			$details   = array_merge(
				array_map(
					static function ( string $path ): string {
						/* translators: %s: file path. */
						return sprintf( __( '%s (changed)', 'hardenly' ), $path );
					},
					$found['modified']
				),
				array_map(
					static function ( string $path ): string {
						/* translators: %s: file path. */
						return sprintf( __( '%s (not in the plugin)', 'hardenly' ), $path );
					},
					$found['added']
				)
			);
			$results[] = new Result(
				self::ID,
				Result::STATUS_WARNING,
				Result::SEVERITY_HIGH,
				/* translators: %d: number of files. */
				sprintf( _n( '%d plugin file differs from WordPress.org', '%d plugin files differ from WordPress.org', $total, 'hardenly' ), $total ),
				__( 'If you did not edit these plugins yourself, reinstall them: deactivate, delete and install them again from Plugins > Add New (settings are usually kept, but back up first). Remove files that are not part of the plugin.', 'hardenly' ),
				array_slice( $details, 0, ChecksumComparer::MAX_PATHS ),
				__( 'Files that differ from the official plugin can be hand edits, or code someone added to the site.', 'hardenly' ),
				'changed',
				$total
			);
		}
		if ( [] !== $unchecked ) {
			$results[] = new Result(
				self::ID,
				Result::STATUS_INFO,
				Result::SEVERITY_INFO,
				/* translators: %d: number of plugins. */
				sprintf( _n( '%d plugin could not be compared with WordPress.org', '%d plugins could not be compared with WordPress.org', count( $unchecked ), 'hardenly' ), count( $unchecked ) ),
				__( 'These plugins are not hosted on WordPress.org (or this version is not), so there are no official checksums for them.', 'hardenly' ),
				$unchecked,
				'',
				'unchecked'
			);
		}

		if ( 0 === $total ) {
			array_unshift( $results, Result::pass( self::ID, __( 'Plugin files from WordPress.org match the official versions.', 'hardenly' ) ) );
		}
		return $results;
	}
}

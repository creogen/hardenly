<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks;

use PHPUnit\Framework\TestCase;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Result;
use Hardenly\Site\Constants;
use Hardenly\Site\Loopback;
use Hardenly\Storage\Settings;

/**
 * Helpers for testing checks: run a check to the end and get its single result.
 */
abstract class CheckTestCase extends TestCase {
	/**
	 * Runs a check, calling it again while it pauses, as the scanner does across batches.
	 *
	 * @param Check                $check    Check to run.
	 * @param array<string, mixed> $settings Settings over the defaults.
	 *
	 * @return Result[]
	 */
	protected function run_check( Check $check, array $settings = [] ): array {
		$results = [];
		$cursor  = null;
		for ( $call = 0; $call < 1000; $call++ ) {
			$context = new Context( array_merge( Settings::DEFAULTS, $settings ), $cursor, PHP_FLOAT_MAX );
			$results = array_merge( $results, $check->run( $context ) );
			if ( ! $context->is_paused() ) {
				return $results;
			}
			$cursor = $context->next_cursor();
		}
		self::fail( 'The check never finished.' );
	}

	/**
	 * Runs a check that returns exactly one result.
	 *
	 * @param Check                $check    Check to run.
	 * @param array<string, mixed> $settings Settings over the defaults.
	 */
	protected function result( Check $check, array $settings = [] ): Result {
		$results = $this->run_check( $check, $settings );
		self::assertCount( 1, $results );
		self::assertSame( $check->id(), $results[0]->check_id() );
		return $results[0];
	}

	protected static function assertPasses( Result $result ): void {
		self::assertSame( Result::STATUS_PASS, $result->status(), $result->message() );
	}

	protected static function assertFails( Result $result, string $severity ): void {
		self::assertSame( Result::STATUS_FAIL, $result->status(), $result->message() );
		self::assertSame( $severity, $result->severity(), $result->message() );
	}

	/**
	 * Code of a sample in tests/fixtures/code, without its "expect:" line. Samples live in
	 * .txt files so the plugin's own suspicious code check does not flag the tests.
	 *
	 * @param string $sample Path under tests/fixtures/code, e.g. "suspicious/eval-base64".
	 */
	protected static function code_sample( string $sample ): string {
		$contents = (string) file_get_contents( __DIR__ . '/../../../fixtures/code/' . $sample . '.txt' );
		return 0 === strpos( $contents, 'expect:' ) ? explode( "\n", $contents, 2 )[1] : $contents;
	}

	/**
	 * Constants with the given values; others are undefined.
	 *
	 * @param array<string, mixed> $values Values by name.
	 */
	protected function constants( array $values ): Constants {
		$constants = $this->createMock( Constants::class );
		$constants->method( 'is_defined' )->willReturnCallback(
			static function ( string $name ) use ( $values ): bool {
				return array_key_exists( $name, $values );
			}
		);
		$constants->method( 'get' )->willReturnCallback(
			static function ( string $name, $default_value = null ) use ( $values ) {
				return array_key_exists( $name, $values ) ? $values[ $name ] : $default_value;
			}
		);
		$constants->method( 'is_true' )->willReturnCallback(
			static function ( string $name ) use ( $values ): bool {
				return ! empty( $values[ $name ] );
			}
		);
		return $constants;
	}

	/**
	 * Loopback requests answered from a map of URL => response; other URLs fail.
	 *
	 * @param array<string, array{code: int, headers?: array<string, string>, body?: string}> $responses Responses by URL.
	 */
	protected function loopback( array $responses ): Loopback {
		$answer   = static function ( string $url ) use ( $responses ): ?array {
			if ( ! isset( $responses[ $url ] ) ) {
				return null;
			}
			return array_merge(
				[
					'headers' => [],
					'body'    => '',
				],
				$responses[ $url ]
			);
		};
		$loopback = $this->createMock( Loopback::class );
		$loopback->method( 'get' )->willReturnCallback( $answer );
		$loopback->method( 'post' )->willReturnCallback( $answer );
		return $loopback;
	}

	/**
	 * A temporary folder with the given files, removed after the test.
	 *
	 * @param array<string, string> $files Contents by relative path.
	 */
	protected function make_dir( array $files ): string {
		$root = sys_get_temp_dir() . '/hardenly-test-' . bin2hex( random_bytes( 6 ) );
		mkdir( $root );
		foreach ( $files as $path => $contents ) {
			if ( ! is_dir( dirname( $root . '/' . $path ) ) ) {
				mkdir( dirname( $root . '/' . $path ), 0777, true );
			}
			file_put_contents( $root . '/' . $path, $contents );
		}
		$this->temp_dirs[] = $root;
		return $root;
	}

	/**
	 * @var string[]
	 */
	private $temp_dirs = [];

	protected function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			self::remove( $dir );
		}
		$this->temp_dirs = [];
	}

	private static function remove( string $path ): void {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( array_diff( (array) scandir( $path ), [ '.', '..' ] ) as $name ) {
				self::remove( $path . '/' . $name );
			}
			rmdir( $path );
		} else {
			unlink( $path );
		}
	}
}

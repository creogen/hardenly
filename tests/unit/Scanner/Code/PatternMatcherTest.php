<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Code;

use PHPUnit\Framework\TestCase;
use Sitewarden\Scanner\Code\PatternMatcher;
use Sitewarden\Scanner\Result;

/**
 * Samples are in tests/fixtures/code, as .txt files so no tool treats them as live code.
 * Each suspicious sample names the rule it should match on its first line ("expect: rule").
 */
class PatternMatcherTest extends TestCase {
	private const FIXTURES = __DIR__ . '/../../../fixtures/code';

	/**
	 * @return array<string, array{string}>
	 */
	private static function samples( string $kind ): array {
		$samples = [];
		foreach ( (array) glob( self::FIXTURES . '/' . $kind . '/*.txt' ) as $file ) {
			$samples[ basename( (string) $file ) ] = [ (string) $file ];
		}
		return $samples;
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function suspicious_samples(): array {
		return self::samples( 'suspicious' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function benign_samples(): array {
		return self::samples( 'benign' );
	}

	/**
	 * @dataProvider suspicious_samples
	 */
	public function testFind_SuspiciousSample_MatchesItsRule( string $file ): void {
		$contents              = (string) file_get_contents( $file );
		list( $header, $code ) = explode( "\n", $contents, 2 );
		$expected              = trim( substr( $header, strlen( 'expect:' ) ) );

		$rules = array_column( ( new PatternMatcher() )->find( $code ), 'rule' );

		self::assertContains( $expected, $rules );
	}

	/**
	 * @dataProvider benign_samples
	 */
	public function testFind_BenignSample_MatchesNothing( string $file ): void {
		self::assertSame( [], ( new PatternMatcher() )->find( (string) file_get_contents( $file ) ) );
	}

	/**
	 * A sample's code without its "expect:" line.
	 */
	private static function sample( string $name ): string {
		return explode( "\n", (string) file_get_contents( self::FIXTURES . '/suspicious/' . $name . '.txt' ), 2 )[1];
	}

	public function testFind_Match_ReportsLineSeverityAndExcerpt(): void {
		$code = str_replace( '<?php', "<?php\n// A comment.\n", self::sample( 'eval-base64' ) );

		$matches = ( new PatternMatcher() )->find( $code );

		self::assertSame(
			[
				[
					'rule'     => 'eval_decode',
					'severity' => Result::SEVERITY_HIGH,
					'line'     => 4,
					// Split so this test file does not match the rule itself.
					'excerpt'  => 'eval' . "(base64_decode('ZWNobyAnaGVsbG8nOw==')); ",
				],
			],
			$matches
		);
	}

	public function testFind_ExcerptWithBinary_IsSingleLinePrintableAndShort(): void {
		$code = str_replace( "('", "('\xff\xfe" . str_repeat( 'A', 200 ), self::sample( 'eval-base64' ) );

		$excerpt = ( new PatternMatcher() )->find( $code )[0]['excerpt'];

		self::assertLessThanOrEqual( 60, strlen( $excerpt ) );
		self::assertSame( 1, preg_match( '~^[\x20-\x7E]+$~', $excerpt ) );
	}

	public function testFind_OwnSourceCode_MatchesNothing(): void {
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__ . '/../../../../src' ) );
		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				self::assertSame( [], ( new PatternMatcher() )->find( (string) file_get_contents( $file->getPathname() ) ), $file->getPathname() );
			}
		}
	}

	public function testLabel_EveryRule_HasALabel(): void {
		foreach ( array_keys( PatternMatcher::RULES ) as $rule ) {
			self::assertNotSame( $rule, PatternMatcher::label( $rule ) );
		}
	}
}

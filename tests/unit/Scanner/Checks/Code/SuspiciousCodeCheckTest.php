<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Code;

use Sitewarden\Scanner\Checks\Code\SuspiciousCodeCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class SuspiciousCodeCheckTest extends CheckTestCase {
	public function testRun_CleanFiles_Passes(): void {
		$root = $this->make_dir(
			[
				'index.php'                    => "<?php\nrequire __DIR__ . '/wp-blog-header.php';\n",
				'wp-content/plugins/x/x.php'   => "<?php\nadd_action( 'init', 'x_init' );\n",
				'wp-content/uploads/notes.txt' => self::code_sample( 'suspicious/eval-base64' ),
			]
		);

		self::assertPasses( $this->result( new SuspiciousCodeCheck( null, $root ) ) );
	}

	public function testRun_HiddenCode_WarnsHighWithFileLineAndRule(): void {
		$root = $this->make_dir( [ 'wp-content/themes/t/functions.php' => self::code_sample( 'suspicious/eval-base64' ) ] );

		$result = $this->result( new SuspiciousCodeCheck( null, $root ) );

		self::assertSame( Result::STATUS_WARNING, $result->status() );
		self::assertSame( Result::SEVERITY_HIGH, $result->severity() );
		self::assertCount( 1, $result->details() );
		// Built with sprintf so this test file does not match the rule itself.
		self::assertStringStartsWith( sprintf( 'wp-content/themes/t/functions.php:2 %s(base64_decode(', 'eval' ), $result->details()[0] );
		self::assertStringContainsString( '(decodes and runs hidden code)', $result->details()[0] );
	}

	public function testRun_OnlyMediumPatterns_WarnsMedium(): void {
		$root = $this->make_dir( [ 'a.php' => self::code_sample( 'suspicious/chr-chain' ) ] );

		self::assertSame( Result::SEVERITY_MEDIUM, $this->result( new SuspiciousCodeCheck( null, $root ) )->severity() );
	}

	public function testRun_MixedFiles_UsesTheHighestSeverityAndCountsFiles(): void {
		$root = $this->make_dir(
			[
				'a.php' => self::code_sample( 'suspicious/chr-chain' ),
				'b.php' => self::code_sample( 'suspicious/eval-base64' ),
				'c.inc' => self::code_sample( 'suspicious/eval-base64' ),
			]
		);

		$result = $this->result( new SuspiciousCodeCheck( null, $root ) );

		self::assertSame( Result::SEVERITY_HIGH, $result->severity() );
		self::assertSame( 3, $result->details_total() );
	}

	public function testRun_FileToSkip_IsNotReported(): void {
		$root = $this->make_dir( [ 'wp-content/plugins/scanner/signatures.php' => self::code_sample( 'suspicious/eval-base64' ) ] );

		$result = $this->result( new SuspiciousCodeCheck( null, $root ), [ 'skip_files' => [ 'wp-content/plugins/scanner' ] ] );

		self::assertPasses( $result );
	}

	public function testRun_NotPhp_IsIgnored(): void {
		self::assertPasses( $this->result( new SuspiciousCodeCheck( null, $this->make_dir( [ 'readme.txt' => self::code_sample( 'suspicious/eval-base64' ) ] ) ) ) );
	}
}

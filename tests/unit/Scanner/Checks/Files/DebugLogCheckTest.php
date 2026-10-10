<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Files;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Files\DebugLogCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class DebugLogCheckTest extends CheckTestCase {
	private const LOG_URL = 'https://example.com/wp-content/debug.log';

	/**
	 * @param array<string, string>                 $files     Files in wp-content.
	 * @param array<string, array{code: int}> $responses Loopback responses.
	 */
	private function check( array $files, array $responses ): DebugLogCheck {
		$content = $this->make_dir( $files + [ 'index.php' => '' ] );
		$urls    = $this->createMock( Urls::class );
		$urls->method( 'content_url' )->with( 'debug.log' )->willReturn( self::LOG_URL );
		ServiceFactory::set_custom_urls( $urls );
		return new DebugLogCheck( null, $this->constants( [ 'WP_CONTENT_DIR' => $content ] ), $this->loopback( $responses ) );
	}

	public function testRun_NoLog_Passes(): void {
		self::assertPasses( $this->result( $this->check( [], [] ) ) );
	}

	public function testRun_PublicLog_FailsHigh(): void {
		self::assertFails( $this->result( $this->check( [ 'debug.log' => 'x' ], [ self::LOG_URL => [ 'code' => 200 ] ] ) ), Result::SEVERITY_HIGH );
	}

	public function testRun_BlockedLog_FailsLow(): void {
		self::assertFails( $this->result( $this->check( [ 'debug.log' => 'x' ], [ self::LOG_URL => [ 'code' => 403 ] ] ) ), Result::SEVERITY_LOW );
	}
}

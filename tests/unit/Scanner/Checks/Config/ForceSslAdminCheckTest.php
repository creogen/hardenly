<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Config\ForceSslAdminCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class ForceSslAdminCheckTest extends CheckTestCase {
	/**
	 * @param array<string, mixed> $constants Constant values.
	 */
	private function check( string $site_url, array $constants = [] ): ForceSslAdminCheck {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'site_url' )->willReturn( $site_url );
		ServiceFactory::set_custom_urls( $urls );
		return new ForceSslAdminCheck( $this->constants( $constants ) );
	}

	public function testRun_NoHttps_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( 'http://example.com' ) )->status() );
	}

	public function testRun_HttpsWithoutForce_FailsLow(): void {
		self::assertFails( $this->result( $this->check( 'https://example.com' ) ), Result::SEVERITY_LOW );
	}

	public function testRun_HttpsForced_Passes(): void {
		self::assertPasses( $this->result( $this->check( 'https://example.com', [ 'FORCE_SSL_ADMIN' => true ] ) ) );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Server\HttpsCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class HttpsCheckTest extends CheckTestCase {
	private function check( string $home, string $site ): HttpsCheck {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'home_url' )->willReturn( $home );
		$urls->method( 'site_url' )->willReturn( $site );
		ServiceFactory::set_custom_urls( $urls );
		return new HttpsCheck();
	}

	public function testRun_BothHttps_Passes(): void {
		self::assertPasses( $this->result( $this->check( 'https://example.com', 'https://example.com/wp' ) ) );
	}

	public function testRun_HomeOverHttp_FailsHigh(): void {
		$result = $this->result( $this->check( 'http://example.com', 'https://example.com' ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'http://example.com' ], $result->details() );
	}
}

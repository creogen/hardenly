<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Server\CertificateCheck;
use Hardenly\Scanner\Result;
use Hardenly\Site\TlsProbe;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class CertificateCheckTest extends CheckTestCase {
	private const NOW = 1800000000;

	private function check( string $home, ?int $expires_in_days ): CertificateCheck {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'home_url' )->willReturn( $home );
		$urls->method( 'wp_parse_url' )->willReturnCallback( 'parse_url' );
		ServiceFactory::set_custom_urls( $urls );

		$probe = $this->createMock( TlsProbe::class );
		$probe->method( 'expires_at' )->with( 'example.com', 443 )->willReturn(
			null === $expires_in_days ? null : self::NOW + $expires_in_days * DAY_IN_SECONDS
		);

		return new CertificateCheck(
			$probe,
			static function (): int {
				return self::NOW;
			}
		);
	}

	public function testRun_NoHttps_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( 'http://example.com', 90 ) )->status() );
	}

	public function testRun_UnreadableCertificate_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( 'https://example.com', null ) )->status() );
	}

	/**
	 * @dataProvider expiries
	 */
	public function testRun_Expiry_RatesBySeverity( int $days, string $status, string $severity ): void {
		$result = $this->result( $this->check( 'https://example.com', $days ) );

		self::assertSame( $status, $result->status() );
		self::assertSame( $severity, $result->severity() );
	}

	/**
	 * @return array<string, array{int, string, string}>
	 */
	public function expiries(): array {
		return [
			'plenty of time' => [ 60, Result::STATUS_PASS, Result::SEVERITY_INFO ],
			'within 30 days' => [ 20, Result::STATUS_FAIL, Result::SEVERITY_MEDIUM ],
			'within 14 days' => [ 5, Result::STATUS_FAIL, Result::SEVERITY_HIGH ],
			'expired'        => [ -1, Result::STATUS_FAIL, Result::SEVERITY_CRITICAL ],
		];
	}
}

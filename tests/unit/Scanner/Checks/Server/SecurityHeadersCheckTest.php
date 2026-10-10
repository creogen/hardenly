<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Server\SecurityHeadersCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class SecurityHeadersCheckTest extends CheckTestCase {
	/**
	 * @param array<string, string>|null $headers Home page headers, or null when it cannot be loaded.
	 */
	private function check( string $home, ?array $headers ): SecurityHeadersCheck {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'home_url' )->willReturn( $home );
		ServiceFactory::set_custom_urls( $urls );
		$responses = null === $headers ? [] : [
			$home => [
				'code'    => 200,
				'headers' => $headers,
			],
		];
		return new SecurityHeadersCheck( $this->loopback( $responses ) );
	}

	public function testRun_AllHeaders_Passes(): void {
		$headers = [
			'content-security-policy'   => "frame-ancestors 'self'",
			'x-content-type-options'    => 'nosniff',
			'referrer-policy'           => 'strict-origin-when-cross-origin',
			'strict-transport-security' => 'max-age=31536000',
		];

		self::assertPasses( $this->result( $this->check( 'https://example.com/', $headers ) ) );
	}

	public function testRun_NoHeadersOverHttps_ListsAllFour(): void {
		$result = $this->result( $this->check( 'https://example.com/', [] ) );

		self::assertFails( $result, Result::SEVERITY_LOW );
		self::assertCount( 4, $result->details() );
	}

	public function testRun_NoHeadersOverHttp_DoesNotAskForHsts(): void {
		self::assertCount( 3, $this->result( $this->check( 'http://example.com/', [] ) )->details() );
	}

	public function testRun_HomeNotFound_IsSkipped(): void {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'home_url' )->willReturn( 'https://example.com/' );
		ServiceFactory::set_custom_urls( $urls );
		$check = new SecurityHeadersCheck( $this->loopback( [ 'https://example.com/' => [ 'code' => 500 ] ] ) );

		self::assertSame( Result::STATUS_SKIPPED, $this->result( $check )->status() );
	}

	public function testRun_HomeUnreachable_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( 'https://example.com/', null ) )->status() );
	}
}

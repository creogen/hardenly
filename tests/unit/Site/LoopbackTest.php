<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Site;

use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Http;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Site\Loopback;

class LoopbackTest extends TestCase {
	/**
	 * @var Http&MockObject
	 */
	private $http;

	/**
	 * @var Errors&MockObject
	 */
	private $errors;

	protected function setUp(): void {
		$this->http   = $this->createMock( Http::class );
		$this->errors = $this->createMock( Errors::class );
		$hooks        = $this->createMock( Hooks::class );
		$hooks->method( 'apply_filters' )->willReturnArgument( 1 );
		ServiceFactory::set_custom_http( $this->http );
		ServiceFactory::set_custom_errors( $this->errors );
		ServiceFactory::set_custom_hooks( $hooks );
	}

	public function testGet_Always_DoesNotFollowRedirectsAndLimitsTheBody(): void {
		$this->http->method( 'wp_remote_retrieve_headers' )->willReturn( [] );
		$this->http->expects( self::once() )
			->method( 'wp_remote_get' )
			->with(
				'https://example.com/',
				self::callback(
					static function ( array $args ): bool {
						return 0 === $args['redirection'] && $args['limit_response_size'] > 0 && false === $args['sslverify'];
					}
				)
			)
			->willReturn( [] );

		( new Loopback() )->get( 'https://example.com/' );
	}

	public function testGet_Response_ReturnsCodeLowerCaseHeadersAndBody(): void {
		$this->http->method( 'wp_remote_get' )->willReturn( [ 'response' => [] ] );
		$this->http->method( 'wp_remote_retrieve_response_code' )->willReturn( 200 );
		$this->http->method( 'wp_remote_retrieve_headers' )->willReturn(
			[
				'X-Frame-Options' => 'SAMEORIGIN',
				'Vary'            => [ 'Accept', 'Cookie' ],
			]
		);
		$this->http->method( 'wp_remote_retrieve_body' )->willReturn( 'body' );

		self::assertSame(
			[
				'code'    => 200,
				'headers' => [
					'x-frame-options' => 'SAMEORIGIN',
					'vary'            => 'Accept, Cookie',
				],
				'body'    => 'body',
			],
			( new Loopback() )->get( 'https://example.com/' )
		);
	}

	public function testGet_Error_ReturnsNull(): void {
		$this->http->method( 'wp_remote_get' )->willReturn( new \WP_Error( 'http_request_failed' ) );
		$this->errors->method( 'is_wp_error' )->willReturn( true );

		self::assertNull( ( new Loopback() )->get( 'https://example.com/' ) );
	}
}

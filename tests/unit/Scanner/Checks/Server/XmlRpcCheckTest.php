<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Server\XmlRpcCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class XmlRpcCheckTest extends CheckTestCase {
	private const URL = 'https://example.com/xmlrpc.php';

	/**
	 * @param array<string, array{code: int, body?: string}> $responses Loopback responses.
	 */
	private function check( array $responses ): XmlRpcCheck {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'site_url' )->with( 'xmlrpc.php' )->willReturn( self::URL );
		ServiceFactory::set_custom_urls( $urls );
		return new XmlRpcCheck( $this->loopback( $responses ) );
	}

	public function testRun_Answers_FailsLow(): void {
		$check = $this->check(
			[
				self::URL => [
					'code' => 200,
					'body' => '<?xml version="1.0"?><methodResponse><params></params></methodResponse>',
				],
			]
		);

		self::assertFails( $this->result( $check ), Result::SEVERITY_LOW );
	}

	public function testRun_Fault_Passes(): void {
		$check = $this->check(
			[
				self::URL => [
					'code' => 200,
					'body' => '<methodResponse><fault><value>XML-RPC services are disabled</value></fault></methodResponse>',
				],
			]
		);

		self::assertPasses( $this->result( $check ) );
	}

	public function testRun_Blocked_Passes(): void {
		self::assertPasses( $this->result( $this->check( [ self::URL => [ 'code' => 403 ] ] ) ) );
	}

	public function testRun_Redirect_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( [ self::URL => [ 'code' => 302 ] ] ) )->status() );
	}

	public function testRun_Unreachable_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( [] ) )->status() );
	}
}

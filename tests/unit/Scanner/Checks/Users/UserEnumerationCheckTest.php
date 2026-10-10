<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Users;

use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Users\UserEnumerationCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class UserEnumerationCheckTest extends CheckTestCase {
	private const AUTHOR = 'https://example.com/?author=5';
	private const REST   = 'https://example.com/wp-json/wp/v2/users';

	protected function setUp(): void {
		$urls = $this->createMock( Urls::class );
		$urls->method( 'home_url' )->willReturnCallback(
			static function ( string $path = '' ): string {
				return 'https://example.com' . $path;
			}
		);
		$rest = $this->createMock( RestApi::class );
		$rest->method( 'rest_url' )->willReturn( self::REST );
		$users = $this->createMock( Users::class );
		$users->method( 'get_users' )->willReturn(
			[
				(object) [
					'ID'            => 5,
					'user_nicename' => 'alice',
				],
			]
		);
		ServiceFactory::set_custom_urls( $urls );
		ServiceFactory::set_custom_rest_api( $rest );
		ServiceFactory::set_custom_users( $users );
	}

	public function testRun_BothBlocked_Passes(): void {
		$loopback = $this->loopback(
			[
				self::AUTHOR => [ 'code' => 200 ],
				self::REST   => [ 'code' => 401 ],
			]
		);

		self::assertPasses( $this->result( new UserEnumerationCheck( $loopback ) ) );
	}

	public function testRun_AuthorRedirectAndRestListing_FailsLowWithBothWays(): void {
		$loopback = $this->loopback(
			[
				self::AUTHOR => [
					'code'    => 301,
					'headers' => [ 'location' => 'https://example.com/author/alice/' ],
				],
				self::REST   => [
					'code' => 200,
					'body' => '[{"id":5,"slug":"alice"}]',
				],
			]
		);

		$result = $this->result( new UserEnumerationCheck( $loopback ) );

		self::assertFails( $result, Result::SEVERITY_LOW );
		self::assertCount( 2, $result->details() );
	}

	public function testRun_RedirectFollowedToArchive_FailsLow(): void {
		$loopback = $this->loopback(
			[
				self::AUTHOR => [
					'code' => 200,
					'body' => '<body class="archive author author-alice author-5">',
				],
			]
		);

		self::assertFails( $this->result( new UserEnumerationCheck( $loopback ) ), Result::SEVERITY_LOW );
	}

	public function testRun_PageWithoutAuthorName_Passes(): void {
		$loopback = $this->loopback(
			[
				self::AUTHOR => [
					'code' => 200,
					'body' => '<body class="home blog">',
				],
			]
		);

		self::assertPasses( $this->result( new UserEnumerationCheck( $loopback ) ) );
	}

	public function testRun_SiteUnreachable_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( new UserEnumerationCheck( $this->loopback( [] ) ) )->status() );
	}
}

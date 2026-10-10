<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Versions\CoreVersionCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class CoreVersionCheckTest extends CheckTestCase {
	/**
	 * @param mixed $transient The update_core site transient.
	 */
	private function check( string $installed, $transient ): CoreVersionCheck {
		$transients = $this->createMock( Transient::class );
		$transients->method( 'get_site_transient' )->with( 'update_core' )->willReturn( $transient );
		$site = $this->createMock( SiteIdentity::class );
		$site->method( 'get_bloginfo' )->with( 'version' )->willReturn( $installed );
		ServiceFactory::set_custom_transient( $transients );
		ServiceFactory::set_custom_site_identity( $site );
		return new CoreVersionCheck();
	}

	/**
	 * @param array<int, array{string, string}> $offers Offers as [ response, version ].
	 */
	private static function offers( array $offers ): object {
		return (object) [
			'updates' => array_map(
				static function ( array $offer ): object {
					return (object) [
						'response' => $offer[0],
						'current'  => $offer[1],
					];
				},
				$offers
			),
		];
	}

	public function testRun_NoUpdateCheckYet_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( '6.8.1', false ) )->status() );
	}

	public function testRun_Latest_Passes(): void {
		self::assertPasses( $this->result( $this->check( '6.8.1', self::offers( [ [ 'latest', '6.8.1' ] ] ) ) ) );
	}

	public function testRun_MinorUpdateInSameBranch_FailsHigh(): void {
		$result = $this->result( $this->check( '6.8.1', self::offers( [ [ 'upgrade', '6.8.3' ] ] ) ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertStringContainsString( '6.8.3', $result->message() );
	}

	public function testRun_NewMajorVersion_FailsMediumWithNewestOffer(): void {
		$result = $this->result( $this->check( '6.8.3', self::offers( [ [ 'upgrade', '6.9.1' ], [ 'upgrade', '7.0' ] ] ) ) );

		self::assertFails( $result, Result::SEVERITY_MEDIUM );
		self::assertStringContainsString( '7.0', $result->message() );
	}
}

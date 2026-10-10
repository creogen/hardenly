<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Themes;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Checks\Versions\ThemeUpdatesCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;
use Sitewarden\Tests\Scanner\Checks\FakeTheme;

class ThemeUpdatesCheckTest extends CheckTestCase {
	/**
	 * @param mixed $transient The update_themes site transient.
	 */
	private function check( $transient ): ThemeUpdatesCheck {
		$transients = $this->createMock( Transient::class );
		$transients->method( 'get_site_transient' )->with( 'update_themes' )->willReturn( $transient );
		$themes = $this->createMock( Themes::class );
		$themes->method( 'wp_get_themes' )->willReturn( [ 'twentytwentyfive' => new FakeTheme( 'Twenty Twenty-Five', '1.0' ) ] );
		ServiceFactory::set_custom_transient( $transients );
		ServiceFactory::set_custom_themes( $themes );
		return new ThemeUpdatesCheck();
	}

	public function testRun_NoUpdateCheckYet_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( false ) )->status() );
	}

	public function testRun_NoUpdates_Passes(): void {
		self::assertPasses( $this->result( $this->check( (object) [ 'response' => [] ] ) ) );
	}

	public function testRun_UpdateAvailable_FailsHigh(): void {
		$result = $this->result( $this->check( (object) [ 'response' => [ 'twentytwentyfive' => [ 'new_version' => '1.3' ] ] ] ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'Twenty Twenty-Five 1.0 → 1.3' ], $result->details() );
	}
}

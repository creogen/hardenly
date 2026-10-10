<?php declare( strict_types=1 );

namespace Hardenly\Tests\Admin;

use Hardenly\Admin\NetworkPage;
use Hardenly\Storage\ReportStore;
use Hardenly\Tests\SitesFake;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;

class NetworkPageTest extends TestCase {
	use SitesFake;

	private function render( bool $super_admin ): string {
		$sites = $this->fake_sites( true );
		$sites->method( 'get_sites' )->willReturnCallback(
			static function ( array $args ) {
				if ( ! empty( $args['count'] ) ) {
					return 2;
				}
				return [
					(object) [
						'blog_id' => 1,
						'domain'  => 'example.com',
						'path'    => '/',
					],
					(object) [
						'blog_id' => 2,
						'domain'  => 'example.com',
						'path'    => '/<b>shop</b>/',
					],
				];
			}
		);
		$sites->method( 'get_blog_option' )->willReturnCallback(
			static function ( int $site_id, string $option ) {
				self::assertSame( ReportStore::HISTORY_OPTION, $option );
				return 1 === $site_id ? [
					[
						'score'       => 72,
						'finished_at' => 1800000000,
					],
				] : [];
			}
		);
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->method( 'current_user_can' )->with( 'manage_network_options' )->willReturn( $super_admin );
		$urls = $this->createMock( Urls::class );
		$urls->method( 'get_admin_url' )->willReturnCallback(
			static function ( int $site_id, string $path ): string {
				return "https://example.com/site-$site_id/wp-admin/$path";
			}
		);
		$dates = $this->createMock( Dates::class );
		$dates->method( 'wp_date' )->willReturn( 'October 10, 2026' );
		ServiceFactory::set_custom_capabilities( $capabilities );
		ServiceFactory::set_custom_urls( $urls );
		ServiceFactory::set_custom_dates( $dates );
		ServiceFactory::set_custom_options( $this->createMock( Options::class ) );
		ServiceFactory::set_custom_plugins( $this->createMock( Plugins::class ) );

		ob_start();
		( new NetworkPage() )->render();
		return (string) ob_get_clean();
	}

	public function testRender_SuperAdmin_ListsSitesWithLastScoreAndDashboardLinks(): void {
		$html = $this->render( true );

		self::assertStringContainsString( '<td>72</td>', $html );
		self::assertStringContainsString( 'October 10, 2026', $html );
		self::assertStringContainsString( 'Not scanned yet', $html );
		self::assertStringContainsString( 'https://example.com/site-2/wp-admin/admin.php?page=hardenly', $html );
		self::assertStringNotContainsString( '<b>shop</b>', $html, 'Site paths are escaped.' );
	}

	public function testRender_NotASuperAdmin_OutputsNothing(): void {
		self::assertSame( '', $this->render( false ) );
	}

	public function testRegister_Always_AddsANetworkMenuForSuperAdmins(): void {
		$plugins = $this->createMock( Plugins::class );
		$plugins->expects( self::once() )
			->method( 'add_menu_page' )
			->with( 'Hardenly', 'Hardenly', 'manage_network_options', NetworkPage::SLUG );
		ServiceFactory::set_custom_plugins( $plugins );
		$this->fake_sites( true );

		( new NetworkPage() )->register();
	}
}

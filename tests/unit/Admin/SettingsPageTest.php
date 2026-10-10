<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Settings as SettingsApi;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Admin\SettingsPage;
use Sitewarden\Cron\Schedule;
use Sitewarden\Storage\Settings;

class SettingsPageTest extends TestCase {
	/**
	 * @var SettingsApi&MockObject
	 */
	private $settings_api;

	/**
	 * @var Nonces&MockObject
	 */
	private $nonces;

	protected function setUp(): void {
		$this->settings_api = $this->createMock( SettingsApi::class );
		$this->nonces       = $this->createMock( Nonces::class );
		ServiceFactory::set_custom_settings( $this->settings_api );
		ServiceFactory::set_custom_nonces( $this->nonces );
		ServiceFactory::set_custom_options( $this->createMock( Options::class ) );
		ServiceFactory::set_custom_dates( $this->createMock( Dates::class ) );
	}

	/**
	 * @param array<string, mixed> $values Settings.
	 */
	private function page( array $values, bool $can_manage = true ): SettingsPage {
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( $can_manage );
		ServiceFactory::set_custom_capabilities( $capabilities );
		$settings = $this->createMock( Settings::class );
		$settings->method( 'get' )->willReturn( array_merge( Settings::DEFAULTS, $values ) );
		return new SettingsPage( $settings, $this->createMock( Schedule::class ) );
	}

	private function render( SettingsPage $page ): string {
		ob_start();
		$page->render();
		return (string) ob_get_clean();
	}

	public function testRegister_Always_RegistersTheOptionWithSanitizer(): void {
		$this->settings_api->expects( self::once() )
			->method( 'register_setting' )
			->with(
				'sitewarden',
				'sitewarden_settings',
				self::callback(
					static function ( array $args ): bool {
						return is_callable( $args['sanitize_callback'] ) && false === $args['show_in_rest'];
					}
				)
			);

		$this->page( [] )->register();
	}

	public function testRender_Administrator_OutputsTheFormWithNonce(): void {
		$this->nonces->expects( self::once() )->method( 'wp_nonce_field' )->with( 'sitewarden-options' );

		$html = $this->render( $this->page( [ 'disabled_groups' => [ 'code' ] ] ) );

		self::assertStringContainsString( 'action="options.php"', $html );
		self::assertStringContainsString( 'name="option_page" value="sitewarden"', $html );
		self::assertMatchesRegularExpression( '~name="sitewarden_settings\[groups\]\[config\]" value="1" checked~', $html );
		self::assertMatchesRegularExpression( '~name="sitewarden_settings\[groups\]\[code\]" value="1" >~', $html );
	}

	public function testRender_StoredValues_AreEscaped(): void {
		$html = $this->render(
			$this->page(
				[
					'skip_files' => [ '</textarea><script>alert(1)</script>' ],
					'ignored'    => [
						'config.salts' => [
							'message' => '<img src=x onerror=alert(1)>',
							'at'      => 0,
							'by'      => 'admin',
						],
					],
				]
			)
		);

		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringContainsString( 'name="sitewarden_settings[restore][config.salts]"', $html );
	}

	public function testRender_OtherUser_OutputsNothing(): void {
		self::assertSame( '', $this->render( $this->page( [], false ) ) );
	}
}

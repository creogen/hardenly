<?php declare( strict_types=1 );

namespace Hardenly\Tests\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Settings as SettingsApi;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Hardenly\Admin\SettingsPage;
use Hardenly\Cron\Schedule;
use Hardenly\Storage\Settings;
use Hardenly\Vulnerability\ProviderRegistry;
use Hardenly\Vulnerability\VulnerabilityProvider;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\Service\Users;

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
		$this->transients = $this->createMock( Transient::class );
		ServiceFactory::set_custom_transient( $this->transients );
		$users = $this->createMock( Users::class );
		$users->method( 'get_current_user_id' )->willReturn( 1 );
		ServiceFactory::set_custom_users( $users );
		$urls = $this->createMock( Urls::class );
		$urls->method( 'admin_url' )->willReturnCallback(
			static function ( string $path ): string {
				return 'https://example.com/wp-admin/' . $path;
			}
		);
		ServiceFactory::set_custom_urls( $urls );
		$hooks = $this->createMock( Hooks::class );
		$hooks->method( 'apply_filters' )->willReturnArgument( 1 );
		ServiceFactory::set_custom_hooks( $hooks );
		$this->provider = $this->createMock( VulnerabilityProvider::class );
		$this->provider->method( 'id' )->willReturn( 'fake' );
		$this->provider->method( 'name' )->willReturn( 'Fake DB' );
		$this->provider->method( 'data_sent' )->willReturn( 'I agree to send slugs to Fake DB.' );
		$this->provider->method( 'signup_url' )->willReturn( 'https://fake.example/signup' );
		$this->provider->method( 'terms_url' )->willReturn( 'https://fake.example/terms' );
	}

	/**
	 * @var Transient&MockObject
	 */
	private $transients;

	/**
	 * @var VulnerabilityProvider&MockObject
	 */
	private $provider;

	/**
	 * @param array<string, mixed> $values Settings.
	 */
	private function page( array $values, bool $can_manage = true ): SettingsPage {
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( $can_manage );
		ServiceFactory::set_custom_capabilities( $capabilities );
		$settings = $this->createMock( Settings::class );
		$settings->method( 'get' )->willReturn( array_merge( Settings::DEFAULTS, $values ) );
		return new SettingsPage( $settings, $this->createMock( Schedule::class ), new ProviderRegistry( [ $this->provider ] ) );
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
				'hardenly',
				'hardenly_settings',
				self::callback(
					static function ( array $args ): bool {
						return is_callable( $args['sanitize_callback'] ) && false === $args['show_in_rest'];
					}
				)
			);

		$this->page( [] )->register();
	}

	public function testRender_Administrator_OutputsTheFormWithNonce(): void {
		$this->nonces->expects( self::once() )->method( 'wp_nonce_field' )->with( 'hardenly-options' );

		$html = $this->render( $this->page( [ 'disabled_groups' => [ 'code' ] ] ) );

		self::assertStringContainsString( 'action="options.php"', $html );
		self::assertStringContainsString( 'name="option_page" value="hardenly"', $html );
		self::assertMatchesRegularExpression( '~name="hardenly_settings\[groups\]\[config\]" value="1" checked~', $html );
		self::assertMatchesRegularExpression( '~name="hardenly_settings\[groups\]\[code\]" value="1" >~', $html );
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
		self::assertStringContainsString( 'name="hardenly_settings[restore][config.salts]"', $html );
	}

	public function testRender_SavedKey_ShowsOnlyItsLastCharacters(): void {
		$html = $this->render(
			$this->page(
				[
					'vuln_provider' => 'fake',
					'vuln_api_key'  => 'abcdefgh-SECRET-wxyz',
				]
			)
		);

		self::assertStringNotContainsString( 'SECRET', $html );
		self::assertStringContainsString( 'A key ending in wxyz is saved.', $html );
		self::assertStringContainsString( 'I agree to send slugs to Fake DB.', $html );
		self::assertStringContainsString( 'Lookups do not run until you agree.', $html );
		self::assertStringContainsString( 'name="action" value="hardenly_test_provider"', $html );
	}

	public function testRender_OnlineServices_AreOffByDefault(): void {
		$html = $this->render( $this->page( [] ) );

		self::assertMatchesRegularExpression( '~name="hardenly_settings\[checksums_enabled\]" value="1" >~', $html );
		self::assertMatchesRegularExpression( '~name="hardenly_settings\[vuln_provider\]" value="" checked>~', $html );
		self::assertStringNotContainsString( 'hardenly_test_provider', $html, 'Nothing to test without a key.' );
	}

	public function testTest_ConsentGiven_StoresTheProviderAnswer(): void {
		$this->provider->expects( self::once() )->method( 'test' )->with( 'key' )->willReturn( 'Connected.' );
		$this->transients->expects( self::once() )
			->method( 'set_transient' )
			->with(
				SettingsPage::TEST_TRANSIENT,
				[
					'user'    => 1,
					'ok'      => true,
					'message' => 'Connected.',
				]
			);
		$this->nonces->expects( self::once() )->method( 'check_admin_referer' )->with( SettingsPage::TEST_ACTION );

		$back = $this->page(
			[
				'vuln_provider' => 'fake',
				'vuln_api_key'  => 'key',
				'vuln_consent'  => true,
			]
		)->test();

		self::assertSame( 'https://example.com/wp-admin/admin.php?page=hardenly-settings', $back );
	}

	public function testTest_NoConsent_SendsNothing(): void {
		$this->provider->expects( self::never() )->method( 'test' );

		$this->page(
			[
				'vuln_provider' => 'fake',
				'vuln_api_key'  => 'key',
			]
		)->test();
	}

	public function testRender_OtherUser_OutputsNothing(): void {
		self::assertSame( '', $this->render( $this->page( [], false ) ) );
	}
}

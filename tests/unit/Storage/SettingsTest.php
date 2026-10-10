<?php declare( strict_types=1 );

namespace Hardenly\Tests\Storage;

use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Hardenly\Storage\Settings;
use Hardenly\Tests\OptionsFake;

class SettingsTest extends TestCase {
	use OptionsFake;

	protected function setUp(): void {
		$this->fake_options();
		$sanitization = $this->createMock( Sanitization::class );
		$sanitization->method( 'sanitize_key' )->willReturnCallback(
			static function ( $key ): string {
				return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);
		$sanitization->method( 'sanitize_text_field' )->willReturnCallback(
			static function ( $text ): string {
				return trim( strip_tags( (string) $text ) );
			}
		);
		$sanitization->method( 'sanitize_user' )->willReturnArgument( 0 );
		$sanitization->method( 'sanitize_email' )->willReturnCallback( 'trim' );
		$sanitization->method( 'is_email' )->willReturnCallback(
			static function ( $email ) {
				return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
			}
		);
		ServiceFactory::set_custom_sanitization( $sanitization );
	}

	public function testGet_NothingStored_ReturnsDefaults(): void {
		self::assertSame( Settings::DEFAULTS, ( new Settings() )->get() );
	}

	public function testGet_Stored_OverridesDefaults(): void {
		$this->stored_options[ Settings::OPTION ] = [ 'modified_days' => 3 ];

		self::assertSame( 3, ( new Settings() )->get()['modified_days'] );
	}

	public function testSanitize_InvalidValues_FallsBackOrDropsThem(): void {
		$clean = ( new Settings() )->sanitize(
			[
				'disabled_groups' => [ 'Files', '<b>x</b>', [] ],
				'modified_days'   => 9999,
				'skip_files'      => [ '/wp-content/x.php', '../../etc/passwd', 'a/../b.php', 'ok\\win.php', '', 'wp-content/x.php' ],
				'schedule'        => 'hourly',
				'email_to'        => 'not an email',
				'ignored'         => [
					'config.salts'        => [ 'message' => '<b>Keys</b>' ],
					'bad id!'             => [ 'message' => 'x' ],
					'files.backups:a.sql' => 'not an array',
				],
			]
		);

		self::assertSame( [ 'files', 'bxb' ], $clean['disabled_groups'] );
		self::assertSame( 365, $clean['modified_days'] );
		self::assertSame( [ 'wp-content/x.php', 'ok/win.php' ], $clean['skip_files'] );
		self::assertSame( 'off', $clean['schedule'] );
		self::assertSame( '', $clean['email_to'] );
		self::assertSame( [ 'config.salts' ], array_keys( $clean['ignored'] ) );
		self::assertSame( 'Keys', $clean['ignored']['config.salts']['message'] );
	}

	public function testSanitizeForm_FormSubmission_MapsFieldsAndKeepsIgnoredFindings(): void {
		$this->stored_options[ Settings::OPTION ] = [
			'ignored' => [
				'config.salts'  => [ 'message' => 'Keys' ],
				'server.xmlrpc' => [ 'message' => 'XML-RPC' ],
			],
		];

		$clean = ( new Settings() )->sanitize_form(
			[
				'form'          => '1',
				'all_groups'    => [ 'config', 'files', 'code' ],
				'groups'        => [ 'config' => '1' ],
				'modified_days' => '14',
				'skip_files'    => "wp-content/a.php\r\nwp-content/b.php\n",
				'schedule'      => 'weekly',
				'email_enabled' => '1',
				'email_to'      => 'owner@example.com',
				'restore'       => [ 'server.xmlrpc' => '1' ],
			]
		);

		self::assertSame( [ 'files', 'code' ], $clean['disabled_groups'] );
		self::assertSame( 14, $clean['modified_days'] );
		self::assertSame( [ 'wp-content/a.php', 'wp-content/b.php' ], $clean['skip_files'] );
		self::assertSame( 'weekly', $clean['schedule'] );
		self::assertTrue( $clean['email_enabled'] );
		self::assertFalse( $clean['email_only_changes'], 'Unchecked boxes are off.' );
		self::assertSame( 'owner@example.com', $clean['email_to'] );
		self::assertSame( [ 'config.salts' ], array_keys( $clean['ignored'] ) );
	}

	public function testSanitizeForm_OnlineServices_KeepTheKeyUnlessReplacedOrRemoved(): void {
		$this->stored_options[ Settings::OPTION ] = [
			'vuln_provider' => 'wpscan',
			'vuln_api_key'  => 'saved-key',
			'vuln_consent'  => true,
		];
		$form                                     = [
			'form'              => '1',
			'checksums_enabled' => '1',
			'vuln_provider'     => 'wpscan',
			'vuln_api_key'      => '',
			'vuln_consent'      => '1',
		];
		$settings                                 = new Settings();

		self::assertSame( 'saved-key', $settings->sanitize_form( $form )['vuln_api_key'], 'An empty field keeps the key.' );
		self::assertTrue( $settings->sanitize_form( $form )['checksums_enabled'] );
		self::assertSame( 'new-key', $settings->sanitize_form( [ 'vuln_api_key' => ' new-key ' ] + $form )['vuln_api_key'] );
		self::assertSame( '', $settings->sanitize_form( [ 'vuln_remove_key' => '1' ] + $form )['vuln_api_key'] );
		self::assertSame( '', $settings->sanitize_form( [ 'vuln_provider' => 'patchstack' ] + $form )['vuln_api_key'], 'A new provider needs its own key.' );
		self::assertFalse( $settings->sanitize_form( [ 'vuln_consent' => '' ] + $form )['vuln_consent'] );
	}

	public function testSanitize_NoProvider_NoConsent(): void {
		self::assertFalse( ( new Settings() )->sanitize( [ 'vuln_consent' => true ] )['vuln_consent'] );
	}

	public function testVulnerabilityLookupReady_NeedsProviderKeyAndConsent(): void {
		$this->stored_options[ Settings::OPTION ] = [
			'vuln_provider' => 'wpscan',
			'vuln_api_key'  => 'k',
			'vuln_consent'  => false,
		];
		self::assertFalse( ( new Settings() )->vulnerability_lookup_ready() );

		$this->stored_options[ Settings::OPTION ]['vuln_consent'] = true;
		self::assertTrue( ( new Settings() )->vulnerability_lookup_ready() );
	}

	public function testSanitizeForm_StoredShape_IsSanitizedAsIs(): void {
		$settings = ( new Settings() )->sanitize( [ 'schedule' => 'daily' ] );

		self::assertSame( $settings, ( new Settings() )->sanitize_form( $settings ) );
	}

	public function testIgnoreAndRestore_Always_UpdateTheList(): void {
		$settings = new Settings();

		$settings->ignore( 'config.salts', 'Keys', 'admin' );
		$settings->ignore( 'files.backups', 'Backups', 'admin' );
		$settings->restore( 'config.salts' );

		self::assertSame( [ 'files.backups' ], $settings->ignored_ids() );
		self::assertSame( 'admin', $settings->get()['ignored']['files.backups']['by'] );
	}

	public function testSkipFile_Always_AddsTheCleanPathOnce(): void {
		$settings = new Settings();

		$settings->skip_file( '/wp-content/uploads/x.php' );
		$settings->skip_file( 'wp-content/uploads/x.php' );

		self::assertSame( [ 'wp-content/uploads/x.php' ], $settings->get()['skip_files'] );
	}
}

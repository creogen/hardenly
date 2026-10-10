<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Report;

use Merkushin\Wpal\Service\Formatting;
use Merkushin\Wpal\Service\Mail;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Report\EmailReport;
use Sitewarden\Scanner\Result;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Storage\Settings;

class EmailReportTest extends TestCase {
	private const SETTINGS = [
		'email_enabled'      => true,
		'email_only_changes' => true,
		'email_to'           => '',
	];

	/**
	 * @var Mail&MockObject
	 */
	private $mail;

	protected function setUp(): void {
		$this->mail = $this->createMock( Mail::class );
		$options    = $this->createMock( Options::class );
		$options->method( 'get_option' )->with( 'admin_email' )->willReturn( 'admin@example.com' );
		$urls = $this->createMock( Urls::class );
		$urls->method( 'admin_url' )->willReturn( 'https://example.com/wp-admin/admin.php?page=sitewarden' );
		$site = $this->createMock( SiteIdentity::class );
		$site->method( 'get_bloginfo' )->willReturn( 'My &amp; Site' );
		$formatting = $this->createMock( Formatting::class );
		$formatting->method( 'wp_specialchars_decode' )->willReturnCallback( 'htmlspecialchars_decode' );
		$formatting->method( 'wp_strip_all_tags' )->willReturnCallback(
			static function ( string $text ): string {
				return strip_tags( $text );
			}
		);
		ServiceFactory::set_custom_mail( $this->mail );
		ServiceFactory::set_custom_options( $options );
		ServiceFactory::set_custom_urls( $urls );
		ServiceFactory::set_custom_site_identity( $site );
		ServiceFactory::set_custom_formatting( $formatting );
	}

	/**
	 * @param array<int, array<string, mixed>> $results Results.
	 *
	 * @return array<string, mixed>
	 */
	private static function report( int $score, array $results = [], string $trigger = 'schedule' ): array {
		return [
			'trigger' => $trigger,
			'score'   => $score,
			'counts'  => [
				'critical' => 0,
				'high'     => 1,
				'medium'   => 0,
				'low'      => 0,
			],
			'results' => $results,
		];
	}

	/**
	 * @param array<string, mixed>           $settings Settings.
	 * @param array<int, array<string, mixed>> $history  Scan summaries, newest first.
	 */
	private function email( array $settings, array $history = [] ): EmailReport {
		$store = $this->createMock( Settings::class );
		$store->method( 'get' )->willReturn( $settings );
		$reports = $this->createMock( ReportStore::class );
		$reports->method( 'history' )->willReturn( $history );
		return new EmailReport( $store, $reports );
	}

	public function testShouldSend_EmailOff_IsFalse(): void {
		self::assertFalse( EmailReport::should_send( self::report( 50 ), null, [ 'email_enabled' => false ] + self::SETTINGS ) );
	}

	public function testShouldSend_ManualScan_IsFalse(): void {
		self::assertFalse( EmailReport::should_send( self::report( 50, [], 'manual' ), null, self::SETTINGS ) );
	}

	public function testShouldSend_FirstScan_IsTrue(): void {
		self::assertTrue( EmailReport::should_send( self::report( 90 ), null, self::SETTINGS ) );
	}

	public function testShouldSend_NothingChanged_IsFalse(): void {
		self::assertFalse(
			EmailReport::should_send(
				self::report( 80 ),
				[
					'score'   => 80,
					'serious' => [],
				],
				self::SETTINGS
			)
		);
	}

	public function testShouldSend_ScoreDropped_IsTrue(): void {
		self::assertTrue(
			EmailReport::should_send(
				self::report( 79 ),
				[
					'score'   => 80,
					'serious' => [],
				],
				self::SETTINGS
			)
		);
	}

	public function testShouldSend_NewSeriousProblem_IsTrue(): void {
		$report = self::report( 80, [ Result::fail( 'files.backups', Result::SEVERITY_HIGH, 'Backups' )->to_array() ] );

		self::assertTrue(
			EmailReport::should_send(
				$report,
				[
					'score'   => 75,
					'serious' => [],
				],
				self::SETTINGS
			)
		);
		self::assertFalse(
			EmailReport::should_send(
				$report,
				[
					'score'   => 75,
					'serious' => [ 'files.backups' ],
				],
				self::SETTINGS
			)
		);
	}

	public function testShouldSend_EveryScanWanted_IsTrue(): void {
		$settings = [ 'email_only_changes' => false ] + self::SETTINGS;

		self::assertTrue(
			EmailReport::should_send(
				self::report( 80 ),
				[
					'score'   => 80,
					'serious' => [],
				],
				$settings
			)
		);
	}

	public function testMaybeSend_ShouldSend_MailsTheAdminWithoutFilePaths(): void {
		$report = self::report(
			72,
			[
				Result::fail( 'files.uploads_php', Result::SEVERITY_HIGH, '2 PHP files found in the uploads folder', 'Fix.', [ 'wp-content/uploads/shell.php' ] )->to_array(),
				Result::fail( 'server.xmlrpc', Result::SEVERITY_LOW, 'XML-RPC is enabled' )->to_array(),
				Result::pass( 'config.salts', 'Keys are fine.' )->to_array(),
			]
		);
		$this->mail->expects( self::once() )
			->method( 'wp_mail' )
			->with(
				'admin@example.com',
				'[My & Site] Sitewarden security score: 72 (-6)',
				self::callback(
					static function ( string $body ): bool {
						return false !== strpos( $body, 'Security score: 72 of 100 (-6)' )
							&& false !== strpos( $body, '- [HIGH] 2 PHP files found in the uploads folder' )
							&& false !== strpos( $body, '- [LOW] XML-RPC is enabled' )
							&& false === strpos( $body, 'Keys are fine' )
							&& false === strpos( $body, 'shell.php' )
							&& false !== strpos( $body, 'admin.php?page=sitewarden' );
					}
				)
			);

		$this->email(
			self::SETTINGS,
			[
				[ 'score' => 72 ],
				[
					'score'   => 78,
					'serious' => [],
				],
			]
		)->maybe_send( $report );
	}

	public function testMaybeSend_CustomRecipient_MailsThem(): void {
		$this->mail->expects( self::once() )->method( 'wp_mail' )->with( 'owner@example.com' );

		$this->email( [ 'email_to' => 'owner@example.com' ] + self::SETTINGS )->maybe_send( self::report( 90 ) );
	}

	public function testMaybeSend_ShouldNotSend_SendsNothing(): void {
		$this->mail->expects( self::never() )->method( 'wp_mail' );

		$this->email( [ 'email_enabled' => false ] + self::SETTINGS )->maybe_send( self::report( 90 ) );
	}
}

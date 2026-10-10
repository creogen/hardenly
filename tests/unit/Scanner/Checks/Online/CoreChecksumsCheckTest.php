<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Online;

use Merkushin\Wpal\Service\Localization;
use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Integrity\WordPressOrgChecksums;
use Hardenly\Scanner\Checks\Online\CoreChecksumsCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class CoreChecksumsCheckTest extends CheckTestCase {
	protected function setUp(): void {
		$site = $this->createMock( SiteIdentity::class );
		$site->method( 'get_bloginfo' )->willReturn( '6.8.3' );
		$localization = $this->createMock( Localization::class );
		$localization->method( 'get_locale' )->willReturn( 'de_DE' );
		ServiceFactory::set_custom_site_identity( $site );
		ServiceFactory::set_custom_localization( $localization );
	}

	/**
	 * @param array<string, string[]>|null $checksums What WordPress.org returns.
	 */
	private function check( string $root, ?array $checksums ): CoreChecksumsCheck {
		$source = $this->createMock( WordPressOrgChecksums::class );
		$source->method( 'core' )->with( '6.8.3', 'de_DE' )->willReturn( $checksums );
		return new CoreChecksumsCheck( $source, null, $root );
	}

	public function testRun_TurnedOff_ReportsNothingAndSendsNothing(): void {
		$source = $this->createMock( WordPressOrgChecksums::class );
		$source->expects( self::never() )->method( 'core' );

		self::assertSame( [], $this->run_check( new CoreChecksumsCheck( $source, null, $this->make_dir( [] ) ) ) );
	}

	public function testRun_AllFilesMatch_Passes(): void {
		$root = $this->make_dir( [ 'wp-includes/version.php' => 'v' ] );

		self::assertPasses( $this->result( $this->check( $root, [ 'wp-includes/version.php' => [ md5( 'v' ) ] ] ), [ 'checksums_enabled' => true ] ) );
	}

	public function testRun_ChangedAndAddedFiles_FailsHighForEach(): void {
		$root      = $this->make_dir(
			[
				'wp-includes/version.php' => 'changed',
				'wp-includes/shell.php'   => 'added',
				'wp-content/themes/x.php' => 'ignored',
			]
		);
		$checksums = [
			'wp-includes/version.php'          => [ md5( 'v' ) ],
			'wp-content/themes/twenty/old.php' => [ md5( 'theme files are left out' ) ],
		];

		$results = $this->run_check( $this->check( $root, $checksums ), [ 'checksums_enabled' => true ] );

		self::assertCount( 2, $results );
		self::assertFails( $results[0], Result::SEVERITY_HIGH );
		self::assertSame( [ 'wp-includes/version.php' ], $results[0]->details() );
		self::assertSame( Result::STATUS_WARNING, $results[1]->status() );
		self::assertSame( [ 'wp-includes/shell.php' ], $results[1]->details() );
		self::assertNotSame( $results[0]->key(), $results[1]->key(), 'Each result can be ignored on its own.' );
	}

	public function testRun_MissingFile_IsANote(): void {
		$root = $this->make_dir( [ 'wp-includes/version.php' => 'v' ] );

		$results = $this->run_check(
			$this->check(
				$root,
				[
					'wp-includes/version.php' => [ md5( 'v' ) ],
					'readme.html'             => [ md5( 'r' ) ],
				]
			),
			[ 'checksums_enabled' => true ]
		);

		self::assertSame( Result::STATUS_INFO, $results[0]->status() );
		self::assertSame( [ 'readme.html' ], $results[0]->details() );
	}

	public function testRun_NoChecksums_IsSkipped(): void {
		$result = $this->result( $this->check( $this->make_dir( [] ), null ), [ 'checksums_enabled' => true ] );

		self::assertSame( Result::STATUS_SKIPPED, $result->status() );
	}
}

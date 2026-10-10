<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Online;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Integrity\WordPressOrgChecksums;
use Sitewarden\Scanner\Checks\Online\PluginChecksumsCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class PluginChecksumsCheckTest extends CheckTestCase {
	private const ENABLED = [ 'checksums_enabled' => true ];

	/**
	 * Plugins folder with akismet (on WordPress.org), custom (not) and hello.php (single file).
	 *
	 * @param array<string, string> $akismet Files in the akismet folder.
	 */
	private function check( array $akismet ): PluginChecksumsCheck {
		$files = [
			'custom/custom.php' => '<?php',
			'hello.php'         => '<?php',
		];
		foreach ( $akismet as $path => $contents ) {
			$files[ 'akismet/' . $path ] = $contents;
		}
		$dir = $this->make_dir( $files );

		$plugins = $this->createMock( Plugins::class );
		$plugins->method( 'get_plugins' )->willReturn(
			[
				'akismet/akismet.php' => [
					'Name'    => 'Akismet',
					'Version' => '5.4',
				],
				'custom/custom.php'   => [
					'Name'    => 'Custom',
					'Version' => '1.0',
				],
				'hello.php'           => [
					'Name'    => 'Hello Dolly',
					'Version' => '1.7',
				],
			]
		);
		ServiceFactory::set_custom_plugins( $plugins );

		$source = $this->createMock( WordPressOrgChecksums::class );
		$source->method( 'plugin' )->willReturnCallback(
			static function ( string $slug, string $version ): ?array {
				return 'akismet' === $slug && '5.4' === $version ? [
					'akismet.php'     => [ md5( 'main' ) ],
					'class-admin.php' => [ md5( 'admin' ) ],
				] : null;
			}
		);

		return new PluginChecksumsCheck( $source, null, $this->constants( [ 'WP_PLUGIN_DIR' => $dir ] ) );
	}

	public function testRun_TurnedOff_ReportsNothing(): void {
		self::assertSame( [], $this->run_check( $this->check( [] ) ) );
	}

	public function testRun_OfficialFiles_PassesAndListsUncheckedPlugins(): void {
		$results = $this->run_check(
			$this->check(
				[
					'akismet.php'     => 'main',
					'class-admin.php' => 'admin',
				]
			),
			self::ENABLED
		);

		self::assertPasses( $results[0] );
		self::assertSame( Result::STATUS_INFO, $results[1]->status() );
		self::assertSame( [ 'Custom', 'Hello Dolly' ], $results[1]->details() );
	}

	public function testRun_ChangedAndAddedFiles_WarnsHighWithPaths(): void {
		$results = $this->run_check(
			$this->check(
				[
					'akismet.php'     => 'main, edited',
					'class-admin.php' => 'admin',
					'backdoor.php'    => '<?php',
					'readme.txt'      => 'not PHP',
				]
			),
			self::ENABLED
		);

		self::assertSame( Result::STATUS_WARNING, $results[0]->status() );
		self::assertSame( Result::SEVERITY_HIGH, $results[0]->severity() );
		self::assertSame( [ 'akismet/akismet.php (changed)', 'akismet/backdoor.php (not in the plugin)' ], $results[0]->details() );
	}

	public function testRun_MissingFiles_AreNotReported(): void {
		$results = $this->run_check( $this->check( [ 'akismet.php' => 'main' ] ), self::ENABLED );

		self::assertPasses( $results[0] );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Themes;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Versions\InactiveExtensionsCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;
use Hardenly\Tests\Scanner\Checks\FakeTheme;

class InactiveExtensionsCheckTest extends CheckTestCase {
	/**
	 * @param string[]                 $active_plugins Active plugin files.
	 * @param array<string, FakeTheme> $themes         Installed themes.
	 */
	private function check( array $active_plugins, array $themes ): InactiveExtensionsCheck {
		$plugins = $this->createMock( Plugins::class );
		$plugins->method( 'get_plugins' )->willReturn(
			[
				'akismet/akismet.php' => [ 'Name' => 'Akismet' ],
				'hello.php'           => [ 'Name' => 'Hello Dolly' ],
			]
		);
		$plugins->method( 'is_plugin_active' )->willReturnCallback(
			static function ( string $file ) use ( $active_plugins ): bool {
				return in_array( $file, $active_plugins, true );
			}
		);
		$service = $this->createMock( Themes::class );
		$service->method( 'wp_get_themes' )->willReturn( $themes );
		$service->method( 'get_stylesheet' )->willReturn( 'child' );
		$service->method( 'get_template' )->willReturn( 'parent' );
		ServiceFactory::set_custom_plugins( $plugins );
		ServiceFactory::set_custom_themes( $service );
		return new InactiveExtensionsCheck();
	}

	public function testRun_EverythingUsed_Passes(): void {
		$themes = [
			'child'  => new FakeTheme( 'Child' ),
			'parent' => new FakeTheme( 'Parent' ),
		];

		self::assertPasses( $this->result( $this->check( [ 'akismet/akismet.php', 'hello.php' ], $themes ) ) );
	}

	public function testRun_UnusedPluginsAndThemes_FailsLowKeepingNewestDefaultTheme(): void {
		$themes = [
			'child'            => new FakeTheme( 'Child' ),
			'parent'           => new FakeTheme( 'Parent' ),
			'twentytwentyfour' => new FakeTheme( 'Twenty Twenty-Four', '1.0', '6.4' ),
			'twentytwentyfive' => new FakeTheme( 'Twenty Twenty-Five', '1.0', '6.7' ),
			'old'              => new FakeTheme( 'Old Theme' ),
		];

		$result = $this->result( $this->check( [ 'akismet/akismet.php' ], $themes ) );

		self::assertFails( $result, Result::SEVERITY_LOW );
		self::assertSame( [ 'Plugin: Hello Dolly', 'Theme: Twenty Twenty-Four', 'Theme: Old Theme' ], $result->details() );
	}
}

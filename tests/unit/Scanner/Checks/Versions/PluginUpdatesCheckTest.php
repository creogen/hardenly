<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Versions;

use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Versions\PluginUpdatesCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class PluginUpdatesCheckTest extends CheckTestCase {
	/**
	 * @param mixed $transient The update_plugins site transient.
	 */
	private function check( $transient ): PluginUpdatesCheck {
		$transients = $this->createMock( Transient::class );
		$transients->method( 'get_site_transient' )->with( 'update_plugins' )->willReturn( $transient );
		$plugins = $this->createMock( Plugins::class );
		$plugins->method( 'get_plugins' )->willReturn(
			[
				'akismet/akismet.php' => [
					'Name'    => 'Akismet',
					'Version' => '5.0',
				],
			]
		);
		ServiceFactory::set_custom_transient( $transients );
		ServiceFactory::set_custom_plugins( $plugins );
		return new PluginUpdatesCheck();
	}

	public function testRun_NoUpdateCheckYet_IsSkipped(): void {
		self::assertSame( Result::STATUS_SKIPPED, $this->result( $this->check( false ) )->status() );
	}

	public function testRun_NoUpdates_Passes(): void {
		self::assertPasses( $this->result( $this->check( (object) [ 'response' => [] ] ) ) );
	}

	public function testRun_UpdateForInstalledPlugin_FailsHighWithVersions(): void {
		$transient = (object) [
			'response' => [
				'akismet/akismet.php' => (object) [ 'new_version' => '5.4' ],
				'removed/removed.php' => (object) [ 'new_version' => '2.0' ],
			],
		];

		$result = $this->result( $this->check( $transient ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'Akismet 5.0 → 5.4' ], $result->details() );
	}
}

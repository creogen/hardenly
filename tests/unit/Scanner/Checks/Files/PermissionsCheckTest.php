<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Files;

use Sitewarden\Scanner\Checks\Files\PermissionsCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

/**
 * @requires OS Darwin|Linux
 */
class PermissionsCheckTest extends CheckTestCase {
	public function testRun_SafePermissions_Passes(): void {
		$root = $this->make_dir( [ 'wp-config.php' => '' ] );
		chmod( $root, 0755 );
		chmod( $root . '/wp-config.php', 0640 );

		self::assertPasses( $this->result( new PermissionsCheck( null, $root ) ) );
	}

	public function testRun_WorldWritableConfig_FailsHigh(): void {
		$root = $this->make_dir( [ 'wp-config.php' => '' ] );
		chmod( $root, 0755 );
		chmod( $root . '/wp-config.php', 0666 );

		$result = $this->result( new PermissionsCheck( null, $root ) );

		self::assertFails( $result, Result::SEVERITY_HIGH );
		self::assertSame( [ 'wp-config.php (666)' ], $result->details() );
	}

	public function testRun_ConfigAboveWordPress_IsChecked(): void {
		$above = $this->make_dir(
			[
				'wp-config.php'    => '',
				'public/index.php' => '',
			]
		);
		chmod( $above . '/public', 0755 );
		chmod( $above . '/wp-config.php', 0666 );

		$result = $this->result( new PermissionsCheck( null, $above . '/public' ) );

		self::assertSame( [ '../wp-config.php (666)' ], $result->details() );
	}
}

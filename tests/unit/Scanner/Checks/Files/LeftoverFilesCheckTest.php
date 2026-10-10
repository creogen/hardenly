<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Files;

use Sitewarden\Scanner\Checks\Files\LeftoverFilesCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class LeftoverFilesCheckTest extends CheckTestCase {
	public function testRun_NoLeftovers_Passes(): void {
		self::assertPasses( $this->result( new LeftoverFilesCheck( null, $this->make_dir( [ 'index.php' => '' ] ) ) ) );
	}

	public function testRun_Leftovers_FailsLowAndListsThem(): void {
		$root = $this->make_dir(
			[
				'readme.html'          => '',
				'wp-config-sample.php' => '',
			]
		);

		$result = $this->result( new LeftoverFilesCheck( null, $root ) );

		self::assertFails( $result, Result::SEVERITY_LOW );
		self::assertSame( [ 'readme.html', 'wp-config-sample.php' ], $result->details() );
	}
}

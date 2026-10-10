<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Database;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Database\AutoloadSizeCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class AutoloadSizeCheckTest extends CheckTestCase {
	/**
	 * @param array<string, string> $options Autoloaded options.
	 */
	private function check( array $options ): AutoloadSizeCheck {
		$service = $this->createMock( Options::class );
		$service->method( 'wp_load_alloptions' )->willReturn( $options );
		ServiceFactory::set_custom_options( $service );
		return new AutoloadSizeCheck();
	}

	public function testRun_SmallOptions_Passes(): void {
		self::assertPasses( $this->result( $this->check( [ 'blogname' => 'Site' ] ) ) );
	}

	public function testRun_OverTheLimit_FailsLow(): void {
		$options = [
			'a' => str_repeat( 'x', AutoloadSizeCheck::LIMIT_BYTES ),
			'b' => 'x',
		];

		self::assertFails( $this->result( $this->check( $options ) ), Result::SEVERITY_LOW );
	}
}

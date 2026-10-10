<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Config\DebugDisplayCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class DebugDisplayCheckTest extends CheckTestCase {
	/**
	 * @param array<string, mixed> $constants Constant values.
	 */
	private function check( array $constants, string $environment = 'production' ): DebugDisplayCheck {
		$env = $this->createMock( Environment::class );
		$env->method( 'wp_get_environment_type' )->willReturn( $environment );
		ServiceFactory::set_custom_environment( $env );
		return new DebugDisplayCheck( $this->constants( $constants ) );
	}

	public function testRun_DebugOff_Passes(): void {
		self::assertPasses( $this->result( $this->check( [] ) ) );
	}

	public function testRun_DebugOnDisplayOff_Passes(): void {
		self::assertPasses(
			$this->result(
				$this->check(
					[
						'WP_DEBUG'         => true,
						'WP_DEBUG_DISPLAY' => false,
					]
				)
			)
		);
	}

	public function testRun_DebugOnInProduction_FailsHigh(): void {
		self::assertFails( $this->result( $this->check( [ 'WP_DEBUG' => true ] ) ), Result::SEVERITY_HIGH );
	}

	public function testRun_DebugOnInStaging_FailsLow(): void {
		self::assertFails( $this->result( $this->check( [ 'WP_DEBUG' => true ], 'staging' ) ), Result::SEVERITY_LOW );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Config;

use Merkushin\Wpal\Service\Environment;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Checks\Config\EnvironmentTypeCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

class EnvironmentTypeCheckTest extends CheckTestCase {
	private function check( string $type ): EnvironmentTypeCheck {
		$env = $this->createMock( Environment::class );
		$env->method( 'wp_get_environment_type' )->willReturn( $type );
		ServiceFactory::set_custom_environment( $env );
		return new EnvironmentTypeCheck();
	}

	public function testRun_Production_Passes(): void {
		self::assertPasses( $this->result( $this->check( 'production' ) ) );
	}

	public function testRun_Staging_IsANoteNotAProblem(): void {
		$result = $this->result( $this->check( 'staging' ) );

		self::assertSame( Result::STATUS_INFO, $result->status() );
		self::assertFalse( $result->is_problem() );
	}
}

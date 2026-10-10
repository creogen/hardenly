<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use PHPUnit\Framework\TestCase;
use Hardenly\Scanner\Context;

class ContextTest extends TestCase {
	/**
	 * @var float
	 */
	private $now = 100.0;

	/**
	 * @var int
	 */
	private $memory = 0;

	private function context( float $deadline, int $memory_limit = 0 ): Context {
		return new Context(
			[ 'modified_days' => 7 ],
			[ 'offset' => 3 ],
			$deadline,
			$memory_limit,
			function (): float {
				return $this->now;
			},
			function (): int {
				return $this->memory;
			}
		);
	}

	public function testShouldYield_TimeLeft_IsFalse(): void {
		self::assertFalse( $this->context( 101.0 )->should_yield() );
	}

	public function testShouldYield_DeadlinePassed_IsTrue(): void {
		$context   = $this->context( 101.0 );
		$this->now = 101.0;

		self::assertTrue( $context->should_yield() );
	}

	public function testShouldYield_CloseToMemoryLimit_IsTrue(): void {
		$this->memory = 85;

		self::assertTrue( $this->context( 200.0, 100 )->should_yield() );
	}

	public function testShouldYield_NoMemoryLimit_IgnoresMemory(): void {
		$this->memory = PHP_INT_MAX;

		self::assertFalse( $this->context( 200.0, 0 )->should_yield() );
	}

	public function testPause_Always_KeepsTheNextCursor(): void {
		$context = $this->context( 200.0 );

		$context->pause( [ 'offset' => 10 ] );

		self::assertTrue( $context->is_paused() );
		self::assertSame( [ 'offset' => 10 ], $context->next_cursor() );
		self::assertSame( [ 'offset' => 3 ], $context->cursor() );
	}

	public function testSetting_Missing_ReturnsDefault(): void {
		$context = $this->context( 200.0 );

		self::assertSame( 7, $context->setting( 'modified_days' ) );
		self::assertSame( 'x', $context->setting( 'missing', 'x' ) );
	}
}

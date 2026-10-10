<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Hardenly\Scanner\CheckRegistry;
use Hardenly\Scanner\Group;

class CheckRegistryTest extends TestCase {
	/**
	 * @var Hooks&MockObject
	 */
	private $hooks;

	protected function setUp(): void {
		$this->hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $this->hooks );
	}

	/**
	 * @param mixed $filtered What the hardenly_checks filter returns.
	 */
	private function filter_returns( $filtered ): void {
		$this->hooks->method( 'apply_filters' )
			->with( 'hardenly_checks', self::anything() )
			->willReturn( $filtered );
	}

	public function testAll_NoFilter_ReturnsDefaultChecksById(): void {
		$this->hooks->method( 'apply_filters' )->willReturnArgument( 1 );
		$check = new FakeCheck( 'config.debug' );

		self::assertSame( [ 'config.debug' => $check ], ( new CheckRegistry( [ $check ] ) )->all() );
	}

	public function testAll_FilterAddsInvalidEntries_DropsThem(): void {
		$valid = new FakeCheck( 'valid' );
		$this->filter_returns( [ $valid, 'not a check', new FakeCheck( 'Invalid ID!' ), new FakeCheck( 'valid' ) ] );

		self::assertSame( [ 'valid' => $valid ], ( new CheckRegistry( [] ) )->all() );
	}

	public function testAll_FilterReturnsNoArray_KeepsDefaultChecks(): void {
		$check = new FakeCheck( 'config.debug' );
		$this->filter_returns( null );

		self::assertSame( [ 'config.debug' ], array_keys( ( new CheckRegistry( [ $check ] ) )->all() ) );
	}

	public function testAll_Always_OrdersByGroupThenRegistration(): void {
		$this->filter_returns(
			[
				new FakeCheck( 'code.a', Group::CODE ),
				new FakeCheck( 'custom.a', 'custom' ),
				new FakeCheck( 'config.a', Group::CONFIG ),
				new FakeCheck( 'code.b', Group::CODE ),
				new FakeCheck( 'config.b', Group::CONFIG ),
			]
		);

		self::assertSame(
			[ 'config.a', 'config.b', 'code.a', 'code.b', 'custom.a' ],
			array_keys( ( new CheckRegistry( [] ) )->all() )
		);
	}

	public function testEnabledIds_DisabledGroup_LeavesItOut(): void {
		$this->filter_returns( [ new FakeCheck( 'config.a', Group::CONFIG ), new FakeCheck( 'files.a', Group::FILES ) ] );

		self::assertSame( [ 'config.a' ], ( new CheckRegistry( [] ) )->enabled_ids( [ Group::FILES ] ) );
	}

	public function testGet_UnknownId_ReturnsNull(): void {
		$this->filter_returns( [] );

		self::assertNull( ( new CheckRegistry( [] ) )->get( 'missing' ) );
	}
}

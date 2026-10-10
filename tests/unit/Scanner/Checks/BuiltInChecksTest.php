<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks;

use PHPUnit\Framework\TestCase;
use Sitewarden\Scanner\Checks\BuiltInChecks;
use Sitewarden\Scanner\Group;
use Sitewarden\Users\LastLogin;

class BuiltInChecksTest extends TestCase {
	public function testAll_Always_HaveUniqueValidIdsInKnownGroups(): void {
		$ids = [];
		foreach ( BuiltInChecks::all( $this->createMock( LastLogin::class ) ) as $check ) {
			self::assertMatchesRegularExpression( '/^[a-z]+\.[a-z_]+$/', $check->id() );
			self::assertStringStartsWith( $check->group() . '.', $check->id() );
			self::assertContains( $check->group(), Group::ORDER );
			self::assertNotSame( '', $check->title() );
			$ids[] = $check->id();
		}

		self::assertSame( array_unique( $ids ), $ids );
		self::assertGreaterThanOrEqual( 30, count( $ids ) );
	}
}

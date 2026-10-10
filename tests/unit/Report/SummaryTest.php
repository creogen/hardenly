<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Report;

use PHPUnit\Framework\TestCase;
use Sitewarden\Report\Summary;
use Sitewarden\Scanner\Result;
use Sitewarden\Scanner\Score;

class SummaryTest extends TestCase {
	/**
	 * @return array<string, mixed>
	 */
	private static function report(): array {
		return [
			'id'      => 'r1',
			'checks'  => [
				'a' => [ 'group' => 'config' ],
				'b' => [ 'group' => 'files' ],
				'c' => [ 'group' => 'users' ],
			],
			'results' => [
				Result::fail( 'a', Result::SEVERITY_HIGH, 'A' )->to_array(),
				Result::warning( 'b', Result::SEVERITY_MEDIUM, 'B' )->to_array(),
				Result::pass( 'c', 'C' )->to_array(),
				Result::skipped( 'c', 'Not run' )->to_array(),
				[ 'broken' => true ],
			],
		];
	}

	public function testApply_NothingIgnored_CountsEverything(): void {
		$report = ( new Summary( new Score() ) )->apply( self::report(), [] );

		self::assertSame( 77, $report['score'] );
		self::assertSame( 1, $report['counts']['high'] );
		self::assertSame( 1, $report['counts']['medium'] );
		self::assertSame( 1, $report['passed'] );
		self::assertSame( 1, $report['skipped'] );
		self::assertSame( 0, $report['ignored'] );
		self::assertCount( 4, $report['results'], 'Invalid results are dropped.' );
		self::assertSame( [ 'a', 'b', 'c', 'c' ], array_column( $report['results'], 'id' ) );
	}

	public function testApply_IgnoredFinding_IsLeftOutOfScoreAndCounts(): void {
		$report = ( new Summary( new Score() ) )->apply( self::report(), [ 'a' ] );

		self::assertSame( 92, $report['score'] );
		self::assertSame( 0, $report['counts']['high'] );
		self::assertSame( 1, $report['ignored'] );
		self::assertTrue( $report['results'][0]['ignored'] );
		self::assertFalse( $report['results'][1]['ignored'] );
	}

	public function testApply_IgnoringAPass_ChangesNothing(): void {
		$report = ( new Summary( new Score() ) )->apply( self::report(), [ 'c' ] );

		self::assertSame( 0, $report['ignored'] );
		self::assertSame( 1, $report['passed'] );
	}

	public function testFindingId_WithKey_AddsIt(): void {
		self::assertSame( 'files.x', Summary::finding_id( [ 'check_id' => 'files.x' ] ) );
		self::assertSame(
			'files.x:a.php',
			Summary::finding_id(
				[
					'check_id' => 'files.x',
					'key'      => 'a.php',
				]
			)
		);
	}

	public function testSeriousIds_Always_ListsHighAndCriticalProblemsNotIgnored(): void {
		$report = ( new Summary( new Score() ) )->apply( self::report(), [] );

		self::assertSame( [ 'a' ], Summary::serious_ids( $report ) );
		self::assertSame( [], Summary::serious_ids( ( new Summary( new Score() ) )->apply( self::report(), [ 'a' ] ) ) );
	}
}

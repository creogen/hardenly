<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use PHPUnit\Framework\TestCase;
use Hardenly\Scanner\Result;
use Hardenly\Scanner\Score;

class ScoreTest extends TestCase {
	private const GROUPS = [
		'a' => 'config',
		'b' => 'files',
	];

	public function testCalculate_NoProblems_Is100(): void {
		$results = [ Result::pass( 'a', 'ok' ), Result::info( 'a', 'note' ), Result::skipped( 'b', 'skipped' ) ];

		self::assertSame( 100, ( new Score() )->calculate( $results, self::GROUPS ) );
	}

	/**
	 * @dataProvider severities
	 */
	public function testCalculate_OneProblem_SubtractsItsWeight( string $severity, int $expected ): void {
		$results = [ Result::fail( 'a', $severity, 'problem' ) ];

		self::assertSame( $expected, ( new Score() )->calculate( $results, self::GROUPS ) );
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public function severities(): array {
		return [
			'critical' => [ Result::SEVERITY_CRITICAL, 75 ],
			'high'     => [ Result::SEVERITY_HIGH, 85 ],
			'medium'   => [ Result::SEVERITY_MEDIUM, 92 ],
			'low'      => [ Result::SEVERITY_LOW, 97 ],
			'info'     => [ Result::SEVERITY_INFO, 100 ],
		];
	}

	public function testCalculate_Warning_CountsLikeAFailure(): void {
		$results = [ Result::warning( 'a', Result::SEVERITY_MEDIUM, 'suspicious' ) ];

		self::assertSame( 92, ( new Score() )->calculate( $results, self::GROUPS ) );
	}

	public function testCalculate_ManyProblemsInOneGroup_CapsTheGroup(): void {
		$results = array_fill( 0, 5, Result::fail( 'a', Result::SEVERITY_CRITICAL, 'problem' ) );

		self::assertSame( 60, ( new Score() )->calculate( $results, self::GROUPS ) );
	}

	public function testCalculate_ProblemsInManyGroups_NeverGoesBelowZero(): void {
		$results = [];
		$groups  = [];
		foreach ( range( 1, 5 ) as $i ) {
			$groups[ "check$i" ] = "group$i";
			$results[]           = Result::fail( "check$i", Result::SEVERITY_CRITICAL, 'problem' );
			$results[]           = Result::fail( "check$i", Result::SEVERITY_CRITICAL, 'problem' );
		}

		self::assertSame( 0, ( new Score() )->calculate( $results, $groups ) );
	}

	public function testCalculate_CustomWeights_UsesThem(): void {
		$score   = new Score(
			[
				'severity'  => [ Result::SEVERITY_HIGH => 50 ],
				'group_cap' => 60,
			]
		);
		$results = [ Result::fail( 'a', Result::SEVERITY_HIGH, 'p' ), Result::fail( 'a', Result::SEVERITY_LOW, 'p' ) ];

		self::assertSame( 47, $score->calculate( $results, self::GROUPS ) );
	}

	public function testConstruct_InvalidWeights_KeepsDefaults(): void {
		$score   = new Score(
			[
				'severity'  => [ Result::SEVERITY_HIGH => 'lots' ],
				'group_cap' => [],
			]
		);
		$results = [ Result::fail( 'a', Result::SEVERITY_HIGH, 'p' ) ];

		self::assertSame( 85, $score->calculate( $results, self::GROUPS ) );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Hardenly\Scanner\Result;

class ResultTest extends TestCase {
	public function testConstruct_UnknownStatus_Throws(): void {
		$this->expectException( InvalidArgumentException::class );

		new Result( 'check', 'infected', Result::SEVERITY_HIGH, 'Message' );
	}

	public function testConstruct_UnknownSeverity_Throws(): void {
		$this->expectException( InvalidArgumentException::class );

		new Result( 'check', Result::STATUS_FAIL, 'urgent', 'Message' );
	}

	public function testConstruct_ManyDetails_KeepsFirstAndCountsAll(): void {
		$details = array_map( 'strval', range( 1, Result::MAX_DETAILS + 20 ) );

		$result = Result::fail( 'check', Result::SEVERITY_HIGH, 'Message', '', $details );

		self::assertCount( Result::MAX_DETAILS, $result->details() );
		self::assertSame( '1', $result->details()[0] );
		self::assertSame( Result::MAX_DETAILS + 20, $result->details_total() );
	}

	public function testConstruct_LongMultibyteDetail_CutsWithoutBreakingCharacters(): void {
		$result = Result::fail( 'check', Result::SEVERITY_LOW, 'Message', '', [ str_repeat( 'ж', 600 ) ] );

		$detail = $result->details()[0];
		self::assertSame( Result::MAX_DETAIL_LENGTH, mb_strlen( $detail ) );
		self::assertStringEndsWith( '...', $detail );
		self::assertSame( 1, preg_match( '//u', $detail ), 'Detail is valid UTF-8.' );
	}

	public function testFromArray_ToArray_RestoresTheResult(): void {
		$result = new Result( 'files.uploads_php', Result::STATUS_FAIL, Result::SEVERITY_HIGH, 'PHP in uploads', 'Remove them.', [ 'a.php' ], 'Why.', 'key', 3 );

		self::assertSame( $result->to_array(), Result::from_array( $result->to_array() )->to_array() );
	}

	public function testFromArray_InvalidData_Throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Result::from_array( [ 'check_id' => 'check' ] );
	}

	public function testIsProblem_FailuresAndWarnings_AreProblems(): void {
		self::assertTrue( Result::fail( 'c', Result::SEVERITY_LOW, 'm' )->is_problem() );
		self::assertTrue( Result::warning( 'c', Result::SEVERITY_LOW, 'm' )->is_problem() );
		self::assertFalse( Result::pass( 'c', 'm' )->is_problem() );
		self::assertFalse( Result::info( 'c', 'm' )->is_problem() );
		self::assertFalse( Result::skipped( 'c', 'm' )->is_problem() );
	}
}

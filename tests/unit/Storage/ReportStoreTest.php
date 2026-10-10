<?php declare( strict_types=1 );

namespace Hardenly\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Hardenly\Storage\ReportStore;
use Hardenly\Tests\OptionsFake;

class ReportStoreTest extends TestCase {
	use OptionsFake;

	protected function setUp(): void {
		$this->fake_options();
	}

	public function testLatest_NothingStored_IsNull(): void {
		self::assertNull( ( new ReportStore() )->latest() );
	}

	public function testSave_Always_StoresReportAndSummary(): void {
		$store  = new ReportStore();
		$report = [
			'id'          => 'r1',
			'finished_at' => 100,
			'score'       => 80,
			'counts'      => [ 'high' => 1 ],
			'results'     => [ [ 'message' => 'm' ] ],
		];

		$store->save( $report );

		self::assertSame( $report, $store->latest() );
		self::assertSame(
			[
				[
					'id'          => 'r1',
					'finished_at' => 100,
					'score'       => 80,
					'counts'      => [ 'high' => 1 ],
					'serious'     => [],
				],
			],
			$store->history()
		);
	}

	public function testReplaceLatest_SameReport_UpdatesReportAndItsSummary(): void {
		$store = new ReportStore();
		$store->save(
			[
				'id'    => 'old',
				'score' => 50,
			]
		);
		$store->save(
			[
				'id'    => 'new',
				'score' => 60,
			]
		);

		$store->replace_latest(
			[
				'id'    => 'new',
				'score' => 75,
			]
		);

		self::assertSame( 75, ( $store->latest() ?? [] )['score'] );
		self::assertSame( [ 75, 50 ], array_column( $store->history(), 'score' ) );
	}

	public function testSave_SeriousProblems_AreRememberedInTheSummary(): void {
		$store = new ReportStore();

		$store->save(
			[
				'id'      => 'r1',
				'results' => [
					[
						'check_id' => 'a',
						'key'      => '',
						'status'   => 'fail',
						'severity' => 'high',
					],
					[
						'check_id' => 'b',
						'key'      => '',
						'status'   => 'fail',
						'severity' => 'low',
					],
				],
			]
		);

		self::assertSame( [ 'a' ], $store->history()[0]['serious'] );
	}

	public function testSave_ManyReports_KeepsNewestSummaries(): void {
		$store = new ReportStore();

		foreach ( range( 1, ReportStore::HISTORY_SIZE + 3 ) as $i ) {
			$store->save( [ 'id' => "r$i" ] );
		}

		$history = $store->history();
		self::assertCount( ReportStore::HISTORY_SIZE, $history );
		self::assertSame( 'r' . ( ReportStore::HISTORY_SIZE + 3 ), $history[0]['id'] );
	}
}

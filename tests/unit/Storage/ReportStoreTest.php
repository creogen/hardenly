<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Tests\OptionsFake;

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
				],
			],
			$store->history()
		);
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

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Report;

use PHPUnit\Framework\TestCase;
use Hardenly\Report\ReportRefresher;
use Hardenly\Report\Summary;
use Hardenly\Scanner\Result;
use Hardenly\Scanner\Score;
use Hardenly\Storage\ReportStore;
use Hardenly\Storage\Settings;
use Hardenly\Tests\OptionsFake;

class ReportRefresherTest extends TestCase {
	use OptionsFake;

	protected function setUp(): void {
		$this->fake_options();
	}

	public function testRefresh_IgnoredListChanged_RecomputesTheLatestReport(): void {
		$summary = new Summary( new Score() );
		( new ReportStore() )->save(
			$summary->apply(
				[
					'id'      => 'r1',
					'checks'  => [ 'a' => [ 'group' => 'config' ] ],
					'results' => [ Result::fail( 'a', Result::SEVERITY_HIGH, 'A' )->to_array() ],
				],
				[ 'a' ]
			)
		);
		$this->stored_options[ Settings::OPTION ] = [ 'ignored' => [] ];

		( new ReportRefresher( new ReportStore(), new Settings(), $summary ) )->refresh();

		$report = ( new ReportStore() )->latest();
		self::assertNotNull( $report );
		self::assertSame( 85, $report['score'] );
		self::assertSame( 0, $report['ignored'] );
		self::assertSame( 85, ( new ReportStore() )->history()[0]['score'] );
	}

	public function testRefresh_NoReport_DoesNothing(): void {
		( new ReportRefresher( new ReportStore(), new Settings(), new Summary( new Score() ) ) )->refresh();

		self::assertNull( ( new ReportStore() )->latest() );
	}
}

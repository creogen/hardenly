<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Cron;

use Merkushin\Wpal\Service\Cron;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sitewarden\Cron\Schedule;
use Sitewarden\Scanner\Scanner;
use Sitewarden\Storage\Settings;

class ScheduleTest extends TestCase {
	/**
	 * @var Cron&MockObject
	 */
	private $cron;

	/**
	 * @var Scanner&MockObject
	 */
	private $scanner;

	protected function setUp(): void {
		$this->cron    = $this->createMock( Cron::class );
		$this->scanner = $this->createMock( Scanner::class );
		ServiceFactory::set_custom_cron( $this->cron );
	}

	private function schedule( string $setting ): Schedule {
		$settings = $this->createMock( Settings::class );
		$settings->method( 'get' )->willReturn( [ 'schedule' => $setting ] );
		return new Schedule( $this->scanner, $settings );
	}

	public function testSync_TurnedOn_SchedulesTheEvent(): void {
		$this->cron->method( 'wp_get_schedule' )->willReturn( false );
		$this->cron->expects( self::once() )
			->method( 'wp_schedule_event' )
			->with( self::greaterThan( time() ), 'weekly', Schedule::HOOK );

		$this->schedule( 'weekly' )->sync();
	}

	public function testSync_SameSchedule_LeavesItAlone(): void {
		$this->cron->method( 'wp_get_schedule' )->willReturn( 'daily' );
		$this->cron->expects( self::never() )->method( 'wp_schedule_event' );
		$this->cron->expects( self::never() )->method( 'wp_clear_scheduled_hook' );

		$this->schedule( 'daily' )->sync();
	}

	public function testSync_Changed_Reschedules(): void {
		$this->cron->method( 'wp_get_schedule' )->willReturn( 'daily' );
		$this->cron->expects( self::once() )->method( 'wp_clear_scheduled_hook' )->with( Schedule::HOOK );
		$this->cron->expects( self::once() )->method( 'wp_schedule_event' )->with( self::anything(), 'weekly', Schedule::HOOK );

		$this->schedule( 'weekly' )->sync();
	}

	public function testSync_TurnedOff_RemovesTheEvent(): void {
		$this->cron->method( 'wp_get_schedule' )->willReturn( 'weekly' );
		$this->cron->expects( self::once() )->method( 'wp_clear_scheduled_hook' )->with( Schedule::HOOK );
		$this->cron->expects( self::never() )->method( 'wp_schedule_event' );

		$this->schedule( 'off' )->sync();
	}

	public function testRun_Always_StartsAScheduledScan(): void {
		$this->scanner->expects( self::once() )->method( 'start' )->with( 'schedule' );

		$this->schedule( 'daily' )->run();
	}

	public function testNextRun_NotScheduled_IsNull(): void {
		$this->cron->method( 'wp_next_scheduled' )->willReturn( false );

		self::assertNull( $this->schedule( 'off' )->next_run() );
	}

	public function testClearAll_Always_RemovesBothEvents(): void {
		$this->cron->expects( self::once() )->method( 'wp_clear_scheduled_hook' )->with( Schedule::HOOK );
		$this->cron->expects( self::once() )->method( 'wp_unschedule_hook' )->with( Scanner::TICK_HOOK );

		Schedule::clear_all();
	}
}

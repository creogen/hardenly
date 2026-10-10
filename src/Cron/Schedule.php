<?php declare( strict_types=1 );

namespace Sitewarden\Cron;

use Merkushin\Wpal\Service\Cron;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Scanner;
use Sitewarden\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Automatic scans: a daily or weekly WP-Cron event, kept in line with the "Automatic scan" setting.
 */
class Schedule {
	public const HOOK = 'sitewarden_scheduled_scan';

	/**
	 * First scan after turning the schedule on, in seconds: soon, but not during the save.
	 */
	private const FIRST_DELAY = HOUR_IN_SECONDS;

	/**
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Cron
	 */
	private $cron;

	public function __construct( Scanner $scanner, Settings $settings ) {
		$this->scanner  = $scanner;
		$this->settings = $settings;
		$this->cron     = ServiceFactory::create_cron();
	}

	/**
	 * Schedules, reschedules or removes the event to match the setting. Runs when the settings change.
	 */
	public function sync(): void {
		$schedule = (string) $this->settings->get()['schedule'];
		$current  = $this->cron->wp_get_schedule( self::HOOK );

		if ( 'off' === $schedule ) {
			if ( false !== $current ) {
				$this->cron->wp_clear_scheduled_hook( self::HOOK );
			}
			return;
		}
		if ( $current === $schedule ) {
			return;
		}

		$this->cron->wp_clear_scheduled_hook( self::HOOK );
		$this->cron->wp_schedule_event( time() + self::FIRST_DELAY, $schedule, self::HOOK );
	}

	/**
	 * When the next automatic scan is due, or null when none is scheduled.
	 */
	public function next_run(): ?int {
		$next = $this->cron->wp_next_scheduled( self::HOOK );
		return false === $next ? null : (int) $next;
	}

	/**
	 * Handles the event: starts a scan, unless one is running.
	 */
	public function run(): void {
		$this->scanner->start( 'schedule' );
	}

	/**
	 * Removes all of the plugin's scheduled events. Runs on deactivation.
	 */
	public static function clear_all(): void {
		$cron = ServiceFactory::create_cron();
		$cron->wp_clear_scheduled_hook( self::HOOK );
		$cron->wp_unschedule_hook( Scanner::TICK_HOOK );
	}
}

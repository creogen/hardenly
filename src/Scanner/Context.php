<?php declare( strict_types=1 );

namespace Hardenly\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * What a check gets for one call of Check::run(): the settings, the time and memory left in
 * the current batch, and the cursor where a paused check stopped.
 */
class Context {
	/**
	 * A batch stops when memory use reaches this share of the memory limit.
	 */
	private const MEMORY_SHARE = 0.8;

	/**
	 * @var array<string, mixed>
	 */
	private $settings;

	/**
	 * @var array<string, mixed>|null
	 */
	private $cursor;

	/**
	 * Clock time when the batch must stop.
	 *
	 * @var float
	 */
	private $deadline;

	/**
	 * Memory limit in bytes, or 0 for none.
	 *
	 * @var int
	 */
	private $memory_limit;

	/**
	 * @var callable(): float
	 */
	private $clock;

	/**
	 * @var callable(): int
	 */
	private $memory_usage;

	/**
	 * @var array<string, mixed>|null
	 */
	private $next_cursor = null;

	/**
	 * @param array<string, mixed>      $settings     Plugin settings, see Settings::get().
	 * @param array<string, mixed>|null $cursor       Where a paused check stopped, or null to start.
	 * @param float                     $deadline     Clock time when the batch must stop.
	 * @param int                       $memory_limit Memory limit in bytes, 0 for none.
	 * @param callable|null             $clock        Returns the time in seconds; microtime() by default.
	 * @param callable|null             $memory_usage Returns memory use in bytes; memory_get_usage() by default.
	 */
	public function __construct(
		array $settings,
		?array $cursor,
		float $deadline,
		int $memory_limit = 0,
		?callable $clock = null,
		?callable $memory_usage = null
	) {
		$this->settings     = $settings;
		$this->cursor       = $cursor;
		$this->deadline     = $deadline;
		$this->memory_limit = $memory_limit;
		$this->clock        = $clock ?? static function (): float {
			return microtime( true );
		};
		$this->memory_usage = $memory_usage ?? static function (): int {
			return memory_get_usage();
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * @param string $key           Setting name.
	 * @param mixed  $default_value Returned when the setting is missing.
	 *
	 * @return mixed
	 */
	public function setting( string $key, $default_value = null ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default_value;
	}

	/**
	 * Where the check stopped in the previous batch, or null on its first call.
	 *
	 * @return array<string, mixed>|null
	 */
	public function cursor(): ?array {
		return $this->cursor;
	}

	/**
	 * Seconds left in this batch; negative once over budget.
	 */
	public function time_left(): float {
		return $this->deadline - ( $this->clock )();
	}

	/**
	 * Whether the check should stop and pause() now: out of time or close to the memory limit.
	 */
	public function should_yield(): bool {
		if ( $this->time_left() <= 0 ) {
			return true;
		}
		return $this->memory_limit > 0 && ( $this->memory_usage )() >= $this->memory_limit * self::MEMORY_SHARE;
	}

	/**
	 * Ends this call early; the scanner calls the check again with this cursor.
	 *
	 * The cursor is stored between requests: keep it small and JSON-safe.
	 *
	 * @param array<string, mixed> $cursor Where to continue.
	 */
	public function pause( array $cursor ): void {
		$this->next_cursor = $cursor;
	}

	public function is_paused(): bool {
		return null !== $this->next_cursor;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function next_cursor(): ?array {
		return $this->next_cursor;
	}
}

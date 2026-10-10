<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;

/**
 * A check whose run() is a closure, for scanner tests.
 */
class FakeCheck implements Check {
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var string
	 */
	private $group;

	/**
	 * @var callable
	 */
	private $run;

	/**
	 * Calls of run() so far.
	 *
	 * @var int
	 */
	public $calls = 0;

	/**
	 * @param string        $id    Check id.
	 * @param string        $group Group id.
	 * @param callable|null $run   Receives the Context and returns results; none by default.
	 */
	public function __construct( string $id, string $group = 'config', ?callable $run = null ) {
		$this->id    = $id;
		$this->group = $group;
		$this->run   = $run ?? static function (): array {
			return [];
		};
	}

	public function id(): string {
		return $this->id;
	}

	public function title(): string {
		return 'Check ' . $this->id;
	}

	public function group(): string {
		return $this->group;
	}

	public function run( Context $context ): array {
		++$this->calls;
		return ( $this->run )( $context );
	}
}

<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks;

/**
 * Stand-in for WP_Theme: answers get() for the headers the checks read.
 */
class FakeTheme {
	/**
	 * @var array<string, string>
	 */
	private $headers;

	public function __construct( string $name, string $version = '1.0', string $requires_wp = '' ) {
		$this->headers = [
			'Name'       => $name,
			'Version'    => $version,
			'RequiresWP' => $requires_wp,
		];
	}

	/**
	 * @return string|false
	 */
	public function get( string $header ) {
		return $this->headers[ $header ] ?? false;
	}
}

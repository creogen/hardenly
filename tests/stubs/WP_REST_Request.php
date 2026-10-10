<?php declare( strict_types=1 );

/**
 * Stand-in for WordPress' WP_REST_Request: holds request parameters.
 */
class WP_REST_Request {
	/**
	 * @var array<string, mixed>
	 */
	private $params = [];

	/**
	 * @param string               $method     HTTP method.
	 * @param string               $route      Route.
	 * @param array<string, mixed> $attributes Route attributes.
	 */
	public function __construct( string $method = '', string $route = '', array $attributes = [] ) {
	}

	/**
	 * @param mixed $value Value.
	 */
	public function set_param( string $key, $value ): void {
		$this->params[ $key ] = $value;
	}

	/**
	 * @return mixed
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}
}

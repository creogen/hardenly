<?php declare( strict_types=1 );

/**
 * Stand-in for WordPress' WP_Error: keeps what the plugin passes to it.
 */
class WP_Error {
	/**
	 * @var string
	 */
	public $code;

	/**
	 * @var string
	 */
	public $message;

	/**
	 * @var mixed
	 */
	public $data;

	/**
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function __construct( string $code = '', string $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->data;
	}
}

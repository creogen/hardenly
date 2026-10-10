<?php declare( strict_types=1 );

namespace Sitewarden\Tests;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Replaces the WPAL Options service with an in-memory store, for tests of classes that read
 * and write options.
 */
trait OptionsFake {
	/**
	 * Stored options by name.
	 *
	 * @var array<string, mixed>
	 */
	protected $stored_options = [];

	protected function fake_options(): void {
		/** @var Options&MockObject $options */
		$options = $this->createMock( Options::class );
		$options->method( 'get_option' )->willReturnCallback(
			function ( $name, $default_value = false ) {
				return array_key_exists( $name, $this->stored_options ) ? $this->stored_options[ $name ] : $default_value;
			}
		);
		$options->method( 'update_option' )->willReturnCallback(
			function ( $name, $value ): bool {
				$this->stored_options[ $name ] = $value;
				return true;
			}
		);
		$options->method( 'delete_option' )->willReturnCallback(
			function ( $name ): bool {
				unset( $this->stored_options[ $name ] );
				return true;
			}
		);
		ServiceFactory::set_custom_options( $options );
	}
}

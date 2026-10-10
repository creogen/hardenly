<?php declare( strict_types=1 );

namespace Hardenly\Storage;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * State of the current (or last failed or stopped) scan run, kept between batches.
 */
class RunStore {
	public const OPTION = 'hardenly_run_state';

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get(): ?array {
		$state = $this->options->get_option( self::OPTION, null );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * @param array<string, mixed> $state Run state.
	 */
	public function save( array $state ): void {
		$this->options->update_option( self::OPTION, $state, false );
	}

	public function delete(): void {
		$this->options->delete_option( self::OPTION );
	}
}

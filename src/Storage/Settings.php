<?php declare( strict_types=1 );

namespace Sitewarden\Storage;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings, all in one option.
 */
class Settings {
	public const OPTION = 'sitewarden_settings';

	public const DEFAULTS = [
		// Check groups turned off.
		'disabled_groups' => [],
		// "Recently modified files" reports PHP files changed in this many days.
		'modified_days'   => 7,
		// Paths, relative to ABSPATH, that file checks never report.
		'skip_files'      => [],
	];

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = $this->options->get_option( self::OPTION, [] );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
	}
}

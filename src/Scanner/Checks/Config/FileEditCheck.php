<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Config;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * The theme and plugin file editors in the dashboard.
 */
class FileEditCheck implements Check {
	public const ID = 'config.file_edit';

	/**
	 * @var Constants
	 */
	private $constants;

	public function __construct( ?Constants $constants = null ) {
		$this->constants = $constants ?? new Constants();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'File editing in the dashboard', 'hardenly' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		if ( $this->constants->is_true( 'DISALLOW_FILE_EDIT' ) || $this->constants->is_true( 'DISALLOW_FILE_MODS' ) ) {
			return [ Result::pass( self::ID, __( 'File editing in the dashboard is turned off.', 'hardenly' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_MEDIUM,
				__( 'File editing in the dashboard is turned on', 'hardenly' ),
				__( "Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php.", 'hardenly' ),
				[],
				__( 'Anyone who gets into an administrator account can change theme and plugin code from the dashboard and take over the site.', 'hardenly' )
			),
		];
	}
}

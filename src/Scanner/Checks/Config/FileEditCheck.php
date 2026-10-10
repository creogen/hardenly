<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Config;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Constants;

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
		return __( 'File editing in the dashboard', 'sitewarden' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		if ( $this->constants->is_true( 'DISALLOW_FILE_EDIT' ) || $this->constants->is_true( 'DISALLOW_FILE_MODS' ) ) {
			return [ Result::pass( self::ID, __( 'File editing in the dashboard is turned off.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_MEDIUM,
				__( 'File editing in the dashboard is turned on', 'sitewarden' ),
				__( "Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php.", 'sitewarden' ),
				[],
				__( 'Anyone who gets into an administrator account can change theme and plugin code from the dashboard and take over the site.', 'sitewarden' )
			),
		];
	}
}

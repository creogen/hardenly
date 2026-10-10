<?php declare( strict_types=1 );

namespace Hardenly\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The dashboard screen. The admin script (assets/src/admin) renders it into the root element.
 */
class DashboardPage {
	public const ROOT_ID = 'hardenly-dashboard';

	/**
	 * @var Capabilities
	 */
	private $capabilities;

	public function __construct() {
		$this->capabilities = ServiceFactory::create_capabilities();
	}

	public function render(): void {
		if ( ! $this->capabilities->current_user_can( Menu::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<div id="<?php echo esc_attr( self::ROOT_ID ); ?>">
				<h1><?php esc_html_e( 'Hardenly', 'hardenly' ); ?></h1>
				<p><?php esc_html_e( 'Loading…', 'hardenly' ); ?></p>
				<noscript>
					<div class="notice notice-error"><p><?php esc_html_e( 'The Hardenly dashboard needs JavaScript.', 'hardenly' ); ?></p></div>
				</noscript>
			</div>
		</div>
		<?php
	}
}

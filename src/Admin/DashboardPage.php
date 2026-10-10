<?php declare( strict_types=1 );

namespace Hardenly\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The dashboard screen. The admin script (assets/src/admin) renders it into the root element.
 */
class DashboardPage {
	public const ROOT_ID = 'hardenly-dashboard';

	/**
	 * @var Access
	 */
	private $access;

	public function __construct() {
		$this->access = new Access();
	}

	public function render(): void {
		if ( ! $this->access->can_manage() ) {
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

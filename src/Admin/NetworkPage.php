<?php declare( strict_types=1 );

namespace Hardenly\Admin;

use Hardenly\Storage\ReportStore;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Sites;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Network Admin > Hardenly on multisite: every site with its last scan and a link to its
 * Hardenly dashboard. Each site keeps its own settings and reports.
 */
class NetworkPage {
	public const SLUG = 'hardenly-network';

	/**
	 * Sites listed at most.
	 */
	public const MAX_SITES = 200;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var Sites
	 */
	private $sites;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var Dates
	 */
	private $dates;

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->plugins      = ServiceFactory::create_plugins();
		$this->capabilities = ServiceFactory::create_capabilities();
		$this->sites        = ServiceFactory::create_sites();
		$this->urls         = ServiceFactory::create_urls();
		$this->dates        = ServiceFactory::create_dates();
		$this->options      = ServiceFactory::create_options();
	}

	/**
	 * Adds the page. Runs on `network_admin_menu`.
	 */
	public function register(): void {
		$this->plugins->add_menu_page(
			__( 'Hardenly', 'hardenly' ),
			__( 'Hardenly', 'hardenly' ),
			'manage_network_options',
			self::SLUG,
			[ $this, 'render' ],
			'dashicons-shield'
		);
	}

	public function render(): void {
		if ( ! $this->capabilities->current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$sites = (array) $this->sites->get_sites( [ 'number' => self::MAX_SITES ] );
		$total = (int) $this->sites->get_sites( [ 'count' => true ] );
		// Date formats are per-site settings; the network uses the main site's.
		$format = $this->options->get_option( 'date_format', 'Y-m-d' ) . ' ' . $this->options->get_option( 'time_format', 'H:i' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Hardenly', 'hardenly' ); ?></h1>
			<p><?php esc_html_e( 'Hardenly runs on each site of the network, with its own settings, scans and reports. Only super admins can open it. File, version and configuration checks cover the whole installation, so their results are the same on every site.', 'hardenly' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Site', 'hardenly' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last scan', 'hardenly' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'hardenly' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'hardenly' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $sites as $site ) :
						$site_id = (int) $site->blog_id;
						$history = $this->sites->get_blog_option( $site_id, ReportStore::HISTORY_OPTION, [] );
						$last    = is_array( $history ) && isset( $history[0] ) && is_array( $history[0] ) ? $history[0] : null;
						?>
						<tr>
							<td><?php echo esc_html( $site->domain . $site->path ); ?></td>
							<td><?php echo null === $last ? esc_html__( 'Not scanned yet', 'hardenly' ) : esc_html( (string) $this->dates->wp_date( $format, (int) $last['finished_at'] ) ); ?></td>
							<td><?php echo null === $last ? '—' : esc_html( (string) (int) $last['score'] ); ?></td>
							<td><a href="<?php echo esc_url( (string) $this->urls->get_admin_url( $site_id, 'admin.php?page=' . Menu::SLUG ) ); ?>"><?php esc_html_e( 'Open dashboard', 'hardenly' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $total > count( $sites ) ) : ?>
				<p class="description">
					<?php
					/* translators: 1: sites shown, 2: sites in the network. */
					echo esc_html( sprintf( __( 'Showing %1$d of %2$d sites.', 'hardenly' ), count( $sites ), $total ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

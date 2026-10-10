<?php declare( strict_types=1 );

namespace Hardenly\Admin;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Sites;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Who may use Hardenly: administrators on a single site, super admins on multisite.
 *
 * Reports show server-wide details (file paths, wp-config.php permissions, code findings)
 * that the administrators of single sites in a network are not meant to see, as WordPress
 * hides plugin and theme management from them too.
 */
class Access {
	/**
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var Sites
	 */
	private $sites;

	public function __construct() {
		$this->capabilities = ServiceFactory::create_capabilities();
		$this->sites        = ServiceFactory::create_sites();
	}

	/**
	 * The capability for every Hardenly screen, route and setting.
	 */
	public function capability(): string {
		return $this->sites->is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	public function can_manage(): bool {
		return (bool) $this->capabilities->current_user_can( $this->capability() );
	}
}

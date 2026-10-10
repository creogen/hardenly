<?php declare( strict_types=1 );

namespace Hardenly\Tests;

use Merkushin\Wpal\Service\Sites;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Replaces the WPAL Sites service: a single site by default, or a network.
 */
trait SitesFake {
	/**
	 * @return Sites&MockObject
	 */
	protected function fake_sites( bool $multisite = false ): Sites {
		$sites = $this->createMock( Sites::class );
		$sites->method( 'is_multisite' )->willReturn( $multisite );
		ServiceFactory::set_custom_sites( $sites );
		return $sites;
	}
}

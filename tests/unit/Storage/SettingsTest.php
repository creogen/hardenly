<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Sitewarden\Storage\Settings;
use Sitewarden\Tests\OptionsFake;

class SettingsTest extends TestCase {
	use OptionsFake;

	protected function setUp(): void {
		$this->fake_options();
	}

	public function testGet_NothingStored_ReturnsDefaults(): void {
		self::assertSame( Settings::DEFAULTS, ( new Settings() )->get() );
	}

	public function testGet_Stored_OverridesDefaults(): void {
		$this->stored_options[ Settings::OPTION ] = [ 'modified_days' => 3 ];

		self::assertSame( 3, ( new Settings() )->get()['modified_days'] );
	}

	public function testGet_StoredNotAnArray_ReturnsDefaults(): void {
		$this->stored_options[ Settings::OPTION ] = 'broken';

		self::assertSame( Settings::DEFAULTS, ( new Settings() )->get() );
	}
}

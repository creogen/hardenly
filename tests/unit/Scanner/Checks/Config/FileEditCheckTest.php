<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Scanner\Checks\Config;

use Sitewarden\Scanner\Checks\Config\FileEditCheck;
use Sitewarden\Scanner\Result;
use Sitewarden\Tests\Scanner\Checks\CheckTestCase;

class FileEditCheckTest extends CheckTestCase {
	public function testRun_EditingAllowed_FailsMedium(): void {
		self::assertFails( $this->result( new FileEditCheck( $this->constants( [] ) ) ), Result::SEVERITY_MEDIUM );
	}

	public function testRun_DisallowFileEdit_Passes(): void {
		self::assertPasses( $this->result( new FileEditCheck( $this->constants( [ 'DISALLOW_FILE_EDIT' => true ] ) ) ) );
	}

	public function testRun_DisallowFileMods_Passes(): void {
		self::assertPasses( $this->result( new FileEditCheck( $this->constants( [ 'DISALLOW_FILE_MODS' => true ] ) ) ) );
	}
}

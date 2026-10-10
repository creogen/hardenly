<?php declare( strict_types=1 );

namespace Hardenly\Tests\Scanner\Checks\Config;

use Hardenly\Scanner\Checks\Config\FileEditCheck;
use Hardenly\Scanner\Result;
use Hardenly\Tests\Scanner\Checks\CheckTestCase;

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

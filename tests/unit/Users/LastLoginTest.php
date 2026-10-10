<?php declare( strict_types=1 );

namespace Sitewarden\Tests\Users;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Sitewarden\Users\LastLogin;

class LastLoginTest extends TestCase {
	public function testStart_FirstTime_RecordsTheStart(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_option' )->willReturn( false );
		$options->expects( self::once() )
			->method( 'add_option' )
			->with( LastLogin::SINCE_OPTION, self::isType( 'int' ), '', true );
		ServiceFactory::set_custom_options( $options );

		( new LastLogin() )->start();
	}

	public function testStart_AlreadyStarted_KeepsTheDate(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_option' )->willReturn( 123 );
		$options->expects( self::never() )->method( 'add_option' );
		ServiceFactory::set_custom_options( $options );

		( new LastLogin() )->start();
	}

	public function testRecord_User_StoresTheTime(): void {
		$users = $this->createMock( Users::class );
		$users->expects( self::once() )
			->method( 'update_user_meta' )
			->with( 7, LastLogin::META_KEY, self::isType( 'int' ) );
		ServiceFactory::set_custom_users( $users );

		( new LastLogin() )->record( 'alice', (object) [ 'ID' => 7 ] );
	}

	public function testRecord_NoUser_StoresNothing(): void {
		$users = $this->createMock( Users::class );
		$users->expects( self::never() )->method( 'update_user_meta' );
		ServiceFactory::set_custom_users( $users );

		( new LastLogin() )->record( 'alice' );
	}
}

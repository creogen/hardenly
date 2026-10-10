<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks\Server;

use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;
use Hardenly\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * XML-RPC answering visitors. Few sites need it, and it is a common target for password guessing.
 */
class XmlRpcCheck implements Check {
	public const ID = 'server.xmlrpc';

	private const REQUEST = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Urls
	 */
	private $urls;

	public function __construct( ?Loopback $loopback = null ) {
		$this->loopback = $loopback ?? new Loopback();
		$this->urls     = ServiceFactory::create_urls();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'XML-RPC', 'hardenly' );
	}

	public function group(): string {
		return Group::SERVER;
	}

	public function run( Context $context ): array {
		$response = $this->loopback->post(
			(string) $this->urls->site_url( 'xmlrpc.php' ),
			[
				'headers' => [ 'Content-Type' => 'text/xml' ],
				'body'    => self::REQUEST,
			]
		);
		if ( null === $response ) {
			return [ Result::skipped( self::ID, __( 'xmlrpc.php could not be reached.', 'hardenly' ) ) ];
		}

		$answers = 200 === $response['code'] && false !== strpos( $response['body'], '<methodResponse>' );
		if ( ( $answers && false !== strpos( $response['body'], '<fault>' ) ) || in_array( $response['code'], [ 401, 403, 404, 405, 410 ], true ) ) {
			return [ Result::pass( self::ID, __( 'XML-RPC does not answer visitors.', 'hardenly' ) ) ];
		}
		if ( ! $answers ) {
			return [
				Result::skipped(
					self::ID,
					/* translators: %d: HTTP status code. */
					sprintf( __( 'xmlrpc.php gave an unexpected answer (HTTP %d).', 'hardenly' ), $response['code'] )
				),
			];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				__( 'XML-RPC is enabled', 'hardenly' ),
				__( 'If you do not use the WordPress mobile app, Jetpack or remote publishing, block xmlrpc.php on the server or turn XML-RPC off with a security plugin.', 'hardenly' ),
				[],
				__( 'XML-RPC lets attackers try many passwords in one request, and is used to amplify attacks on other sites.', 'hardenly' )
			),
		];
	}
}

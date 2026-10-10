<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks\Users;

use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Context;
use Sitewarden\Scanner\Group;
use Sitewarden\Scanner\Result;
use Sitewarden\Site\Loopback;

defined( 'ABSPATH' ) || exit;

/**
 * Usernames that visitors can list through author archives or the REST API.
 *
 * Asks the site itself, as a logged-out visitor would.
 */
class UserEnumerationCheck implements Check {
	public const ID = 'users.enumeration';

	/**
	 * @var Loopback
	 */
	private $loopback;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var RestApi
	 */
	private $rest;

	/**
	 * @var Users
	 */
	private $users;

	public function __construct( ?Loopback $loopback = null ) {
		$this->loopback = $loopback ?? new Loopback();
		$this->urls     = ServiceFactory::create_urls();
		$this->rest     = ServiceFactory::create_rest_api();
		$this->users    = ServiceFactory::create_users();
	}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Username listing', 'sitewarden' );
	}

	public function group(): string {
		return Group::USERS;
	}

	public function run( Context $context ): array {
		$ways = [];

		$admins   = (array) $this->users->get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
			]
		);
		$admin    = $admins[0] ?? null;
		$user_id  = is_object( $admin ) && isset( $admin->ID ) ? (int) $admin->ID : 1;
		$nicename = is_object( $admin ) && isset( $admin->user_nicename ) ? (string) $admin->user_nicename : '';

		$author = $this->loopback->get( (string) $this->urls->home_url( '/?author=' . $user_id ) );
		if ( null !== $author && $this->author_page_reveals( $author, $nicename ) ) {
			/* translators: %d: user ID. */
			$ways[] = sprintf( __( 'Author archives: /?author=%d redirects to a URL with the username', 'sitewarden' ), $user_id );
		}

		$rest = $this->loopback->get( (string) $this->rest->rest_url( 'wp/v2/users' ) );
		if ( null !== $rest && 200 === $rest['code'] ) {
			$users = json_decode( $rest['body'], true );
			if ( is_array( $users ) && isset( $users[0]['slug'] ) ) {
				$ways[] = __( 'REST API: /wp-json/wp/v2/users lists users', 'sitewarden' );
			}
		}

		if ( null === $author && null === $rest ) {
			return [ Result::skipped( self::ID, __( 'The site could not be reached to test this.', 'sitewarden' ) ) ];
		}
		if ( [] === $ways ) {
			return [ Result::pass( self::ID, __( 'Visitors cannot list usernames.', 'sitewarden' ) ) ];
		}

		return [
			Result::fail(
				self::ID,
				Result::SEVERITY_LOW,
				__( 'Visitors can list usernames', 'sitewarden' ),
				__( 'Block author archives by ID and the users endpoint for logged-out visitors, with a security or SEO plugin or a few lines of code. Strong passwords and two-factor login matter more than hiding usernames.', 'sitewarden' ),
				$ways,
				__( 'Knowing usernames makes password-guessing attacks easier.', 'sitewarden' )
			),
		];
	}

	/**
	 * Whether the author archive request shows the username: a redirect to /author/<name>/, or, where
	 * the HTTP transport follows redirects anyway, the archive page itself (its body class names the author).
	 *
	 * @param array{code: int, headers: array<string, string>, body: string} $response Response to /?author=N.
	 * @param string                                                         $nicename The user's URL name.
	 */
	private function author_page_reveals( array $response, string $nicename ): bool {
		if ( in_array( $response['code'], [ 301, 302, 307, 308 ], true ) ) {
			return false !== strpos( $response['headers']['location'] ?? '', '/author/' );
		}
		return 200 === $response['code'] && '' !== $nicename
			&& 1 === preg_match( '~\bauthor-' . preg_quote( $nicename, '~' ) . '\b|/author/' . preg_quote( $nicename, '~' ) . '/~', $response['body'] );
	}
}

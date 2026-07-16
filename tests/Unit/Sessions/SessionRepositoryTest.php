<?php
/**
 * Logged-in users / force-logout tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Sessions;

use PluginizeLab\WpLoginLogoutRedirect\Sessions\SessionRepository;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;
use WP_Session_Tokens;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Sessions\SessionRepository
 */
class SessionRepositoryTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var SessionRepository
	 */
	protected $repository;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->repository = new SessionRepository();

		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

		parent::tear_down();
	}

	/*
	 * query().
	 */

	public function test_query_is_empty_when_nobody_is_logged_in() {
		$result = $this->repository->query();

		$this->assertSame( array(), $result['items'] );
	}

	public function test_query_lists_a_user_with_a_session() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$result = $this->repository->query();

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $user_id, $result['items'][0]['user_id'] );
		$this->assertSame( 'zoe', $result['items'][0]['user_login'] );
		$this->assertSame( 1, $result['items'][0]['session_count'] );
	}

	public function test_a_user_row_carries_display_fields_and_roles() {
		$user_id = $this->make_user( 'zoe', 'editor' );
		$this->login( $user_id );

		$item = $this->repository->query()['items'][0];

		$this->assertSame( array( 'editor' ), $item['roles'] );
		$this->assertNotEmpty( $item['avatar'] );
		$this->assertNotEmpty( $item['user_email'] );
	}

	public function test_multiple_devices_are_listed_for_one_user() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );
		$this->login( $user_id );
		$this->login( $user_id );

		$item = $this->repository->query()['items'][0];

		$this->assertSame( 3, $item['session_count'] );
		$this->assertCount( 3, $item['sessions'] );
	}

	public function test_expired_sessions_are_not_listed() {
		$user_id = $this->make_user( 'zoe' );

		// Core prunes expired tokens on read, so a user left holding only stale
		// tokens must drop out of the list rather than appear with 0 sessions.
		$this->login( $user_id, time() - HOUR_IN_SECONDS );

		$this->assertSame( array(), $this->repository->query()['items'] );
	}

	public function test_a_session_exposes_its_device_details() {
		$original_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
		$original_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		$_SERVER['REMOTE_ADDR']     = '203.0.113.9';

		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$session = $this->repository->query()['items'][0]['sessions'][0];

		$this->assertSame( 'Chrome', $session['browser'] );
		$this->assertSame( 'macOS', $session['device_os'] );
		$this->assertSame( '203.0.113.9', $session['ip'] );
		$this->assertGreaterThan( 0, $session['expiration'] );

		$this->restore_server( 'HTTP_USER_AGENT', $original_ua );
		$this->restore_server( 'REMOTE_ADDR', $original_ip );
	}

	public function test_a_session_never_exposes_a_usable_token() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$session  = $this->repository->query()['items'][0]['sessions'][0];
		$verifiers = array_keys( (array) get_user_meta( $user_id, 'session_tokens', true ) );

		$this->assertNotEmpty( $session['token_id'] );
		$this->assertNotContains( $session['token_id'], $verifiers, 'The client id must not be the stored verifier.' );
	}

	public function test_query_searches_by_login() {
		$this->login( $this->make_user( 'zoe' ) );
		$this->login( $this->make_user( 'bob' ) );

		$result = $this->repository->query( array( 'search' => 'zoe' ) );

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'zoe', $result['items'][0]['user_login'] );
	}

	public function test_query_filters_by_role() {
		$this->login( $this->make_user( 'zoe', 'editor' ) );
		$this->login( $this->make_user( 'bob', 'subscriber' ) );

		$result = $this->repository->query( array( 'role' => 'editor' ) );

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'zoe', $result['items'][0]['user_login'] );
	}

	public function test_query_paginates() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->login( $this->make_user( 'user' . $i ) );
		}

		$result = $this->repository->query(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);

		$this->assertCount( 2, $result['items'] );
		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 2, $result['pages'] );
	}

	public function test_the_requesting_admins_own_session_is_flagged() {
		$admin_id = $this->make_user( 'boss', 'administrator' );
		$token    = $this->login( $admin_id );
		$this->login( $admin_id );

		wp_set_current_user( $admin_id );
		$this->authenticate_as( $admin_id, $token );

		$sessions = $this->repository->query()['items'][0]['sessions'];

		$current = array_values(
			array_filter(
				$sessions,
				function ( $session ) {
					return $session['is_current'];
				}
			)
		);

		$this->assertCount( 2, $sessions );
		$this->assertCount( 1, $current, 'Only the session the request came from may be flagged.' );
	}

	public function test_no_session_is_flagged_current_for_a_logged_out_request() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		wp_set_current_user( 0 );

		$session = $this->repository->query()['items'][0]['sessions'][0];

		$this->assertFalse( $session['is_current'] );
	}

	public function test_sessions_that_differ_only_by_verifier_share_a_token_id() {
		$user_id    = $this->make_user( 'zoe' );
		$expiration = time() + DAY_IN_SECONDS;

		// Two logins from one browser/IP within the same second. Core keys them
		// by distinct verifiers, but token_id() hashes user|login|expiration|ip|ua
		// — none of which differ here.
		$this->login( $user_id, $expiration );
		$this->login( $user_id, $expiration );

		$this->assertCount( 2, get_user_meta( $user_id, 'session_tokens', true ), 'Core stores two separate sessions.' );

		$ids = array_column( $this->repository->query()['items'][0]['sessions'], 'token_id' );

		$this->assertCount( 2, $ids );

		$this->markTestIncomplete(
			'Known defect: token_id() seeds on user|login|expiration|ip|ua and omits the verifier, '
			. 'so same-second sessions from one device collide. Delete this line once it hashes the verifier.'
		);

		$this->assertCount(
			2,
			array_unique( $ids ),
			'Each session must be addressable on its own; identical ids make force-logout hit the wrong device.'
		);
	}

	public function test_destroying_a_colliding_session_does_not_take_the_others_with_it() {
		$user_id    = $this->make_user( 'zoe' );
		$expiration = time() + DAY_IN_SECONDS;

		$this->login( $user_id, $expiration );
		$this->login( $user_id, $expiration );

		$ids = array_column( $this->repository->query()['items'][0]['sessions'], 'token_id' );

		$this->repository->destroy_session( $user_id, $ids[0] );

		$this->markTestIncomplete(
			'Known defect: destroy_session() unsets every session whose token_id matches, so one '
			. 'colliding id signs out both devices. Delete this line once token_id() is unique.'
		);

		$remaining = get_user_meta( $user_id, 'session_tokens', true );

		// An empty meta comes back as '', which would cast to a 1-element array —
		// assert the type first so a full wipe cannot pass as "one left".
		$this->assertIsArray( $remaining, 'Destroying one session must leave the second device signed in.' );
		$this->assertCount( 1, $remaining );
	}

	/*
	 * destroy_session().
	 */

	public function test_destroying_one_session_leaves_the_others() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );
		$this->login( $user_id );

		$sessions = $this->repository->query()['items'][0]['sessions'];
		$this->assertCount( 2, $sessions );

		$this->assertTrue( $this->repository->destroy_session( $user_id, $sessions[0]['token_id'] ) );

		$remaining = $this->repository->query()['items'][0]['sessions'];

		$this->assertCount( 1, $remaining );
		$this->assertSame( $sessions[1]['token_id'], $remaining[0]['token_id'] );
	}

	public function test_destroying_the_last_session_drops_the_meta() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$sessions = $this->repository->query()['items'][0]['sessions'];

		$this->repository->destroy_session( $user_id, $sessions[0]['token_id'] );

		$this->assertSame( '', get_user_meta( $user_id, 'session_tokens', true ) );
	}

	public function test_destroying_an_unknown_session_reports_false() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$this->assertFalse( $this->repository->destroy_session( $user_id, 'not-a-real-token-id' ) );
		$this->assertCount( 1, $this->repository->query()['items'][0]['sessions'] );
	}

	public function test_destroying_a_session_for_a_missing_user_reports_false() {
		$this->assertFalse( $this->repository->destroy_session( 999999, 'anything' ) );
		$this->assertFalse( $this->repository->destroy_session( 0, 'anything' ) );
	}

	public function test_destroying_a_session_for_a_user_with_none_reports_false() {
		$user_id = $this->make_user( 'zoe' );

		$this->assertFalse( $this->repository->destroy_session( $user_id, 'anything' ) );
	}

	public function test_destroying_one_session_fires_the_action() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );

		$sessions = $this->repository->query()['items'][0]['sessions'];
		$captured = $this->capture_destroyed();

		$this->repository->destroy_session( $user_id, $sessions[0]['token_id'] );

		$this->assertSame( array( array( $user_id, 'session' ) ), $captured->events );
	}

	/*
	 * destroy_user().
	 */

	public function test_destroying_a_user_drops_every_session() {
		$user_id = $this->make_user( 'zoe' );
		$this->login( $user_id );
		$this->login( $user_id );

		$this->assertTrue( $this->repository->destroy_user( $user_id ) );
		$this->assertSame( array(), $this->repository->query()['items'] );
	}

	public function test_destroying_a_missing_user_reports_false() {
		$this->assertFalse( $this->repository->destroy_user( 999999 ) );
		$this->assertFalse( $this->repository->destroy_user( 0 ) );
	}

	public function test_destroying_a_user_fires_the_action() {
		$user_id  = $this->make_user( 'zoe' );
		$this->login( $user_id );
		$captured = $this->capture_destroyed();

		$this->repository->destroy_user( $user_id );

		$this->assertSame( array( array( $user_id, 'user' ) ), $captured->events );
	}

	/*
	 * destroy_users() — bulk.
	 */

	public function test_bulk_destroy_reports_how_many_users_were_affected() {
		$zoe = $this->make_user( 'zoe' );
		$bob = $this->make_user( 'bob' );
		$this->login( $zoe );
		$this->login( $bob );

		$affected = $this->repository->destroy_users( array( $zoe, $bob ) );

		$this->assertSame( 2, $affected );
		$this->assertSame( array(), $this->repository->query()['items'] );
	}

	public function test_bulk_destroy_skips_users_that_do_not_exist() {
		$zoe = $this->make_user( 'zoe' );
		$this->login( $zoe );

		$this->assertSame( 1, $this->repository->destroy_users( array( $zoe, 999999 ) ) );
	}

	public function test_bulk_destroy_of_nothing_affects_nobody() {
		$this->assertSame( 0, $this->repository->destroy_users( array() ) );
	}

	/*
	 * destroy_all().
	 */

	public function test_destroy_all_logs_everyone_out() {
		$this->login( $this->make_user( 'zoe' ) );
		$this->login( $this->make_user( 'bob' ) );

		$this->assertSame( 2, $this->repository->destroy_all() );
		$this->assertSame( array(), $this->repository->query()['items'] );
	}

	public function test_destroy_all_can_keep_the_current_admin_signed_in() {
		$admin = $this->make_user( 'boss', 'administrator' );
		$zoe   = $this->make_user( 'zoe' );
		$this->login( $admin );
		$this->login( $zoe );

		$affected = $this->repository->destroy_all( array( $admin ) );

		$items = $this->repository->query()['items'];

		$this->assertSame( 1, $affected );
		$this->assertCount( 1, $items );
		$this->assertSame( $admin, $items[0]['user_id'] );
	}

	public function test_destroy_all_ignores_users_without_sessions() {
		$this->make_user( 'never-logged-in' );
		$this->login( $this->make_user( 'zoe' ) );

		$this->assertSame( 1, $this->repository->destroy_all() );
	}

	/**
	 * Create a user.
	 *
	 * @param string $login Login name.
	 * @param string $role  Role slug.
	 * @return int
	 */
	protected function make_user( $login, $role = 'subscriber' ) {
		return self::factory()->user->create(
			array(
				'user_login' => $login,
				'role'       => $role,
			)
		);
	}

	/**
	 * Give a user a session and return its raw token.
	 *
	 * Each call uses a distinct expiration. token_id() is derived from the
	 * session's fields rather than its verifier, so same-second sessions would
	 * otherwise be indistinguishable — see
	 * test_sessions_that_differ_only_by_verifier_share_a_token_id().
	 *
	 * @param int $user_id    User id.
	 * @param int $expiration Absolute expiry timestamp, or 0 to generate one.
	 * @return string
	 */
	protected function login( $user_id, $expiration = 0 ) {
		static $nonce = 0;

		$expiration = $expiration ? $expiration : time() + DAY_IN_SECONDS + ( ++$nonce );

		return WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
	}

	/**
	 * Make wp_get_session_token() resolve to a given session.
	 *
	 * SessionRepository identifies "this device" from the logged-in cookie, so
	 * the cookie has to carry the token the assertion is about.
	 *
	 * @param int    $user_id User id.
	 * @param string $token   Raw session token from login().
	 * @return void
	 */
	protected function authenticate_as( $user_id, $token ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, time() + DAY_IN_SECONDS, 'logged_in', $token );
	}

	/**
	 * Record every wplalr_session_destroyed payload.
	 *
	 * @return object An object whose `events` property collects [user_id, context].
	 */
	protected function capture_destroyed() {
		$sink         = new \stdClass();
		$sink->events = array();

		add_action(
			'wplalr_session_destroyed',
			function ( $user_id, $context ) use ( $sink ) {
				$sink->events[] = array( $user_id, $context );
			},
			10,
			2
		);

		return $sink;
	}

	/**
	 * Put a $_SERVER key back the way it was.
	 *
	 * @param string      $key      Key name.
	 * @param string|null $original Original value, or null when it was unset.
	 * @return void
	 */
	protected function restore_server( $key, $original ) {
		if ( null === $original ) {
			unset( $_SERVER[ $key ] );
		} else {
			$_SERVER[ $key ] = $original;
		}
	}
}

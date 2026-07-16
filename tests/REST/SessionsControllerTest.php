<?php
/**
 * Logged-in users REST endpoint tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\REST;

use WP_Session_Tokens;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\REST\SessionsController
 */
class SessionsControllerTest extends RestTestCase {

	/*
	 * Routing + permissions.
	 */

	public function test_the_session_routes_are_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wplalr/v1/sessions', $routes );
		$this->assertArrayHasKey( '/wplalr/v1/sessions/destroy-all', $routes );
		$this->assertArrayHasKey( '/wplalr/v1/sessions/bulk-destroy', $routes );
		$this->assertArrayHasKey( '/wplalr/v1/sessions/(?P<user>\d+)', $routes );
	}

	public function test_an_admin_can_list_sessions() {
		$this->acting_as( 'administrator' );

		$this->assertSame( 200, $this->dispatch( 'GET', '/sessions' )->get_status() );
	}

	public function test_a_subscriber_cannot_list_sessions() {
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'GET', '/sessions' )->get_status() );
	}

	public function test_a_logged_out_visitor_cannot_list_sessions() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( 'GET', '/sessions' )->get_status() );
	}

	public function test_a_subscriber_cannot_force_another_user_out() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'DELETE', '/sessions/' . $victim )->get_status() );
		$this->assertNotEmpty( get_user_meta( $victim, 'session_tokens', true ), 'The victim must stay signed in.' );
	}

	public function test_a_subscriber_cannot_force_everyone_out() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'POST', '/sessions/destroy-all' )->get_status() );
		$this->assertNotEmpty( get_user_meta( $victim, 'session_tokens', true ) );
	}

	public function test_a_subscriber_cannot_bulk_destroy() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'subscriber' );

		$response = $this->dispatch( 'POST', '/sessions/bulk-destroy', array( 'user_ids' => array( $victim ) ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertNotEmpty( get_user_meta( $victim, 'session_tokens', true ) );
	}

	/*
	 * Listing.
	 */

	public function test_the_list_returns_users_with_sessions() {
		$user_id = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'administrator' );

		$data = $this->dispatch( 'GET', '/sessions' )->get_data();

		$logins = array_column( $data, 'user_login' );

		$this->assertContains( 'zoe', $logins );

		$row = $data[ array_search( 'zoe', $logins, true ) ];

		$this->assertSame( $user_id, $row['user_id'] );
		$this->assertSame( 1, $row['session_count'] );
	}

	public function test_the_list_sends_pagination_headers() {
		$this->logged_in_user( 'zoe' );
		$this->logged_in_user( 'bob' );

		$this->acting_as( 'administrator' );

		$headers = $this->dispatch( 'GET', '/sessions', array( 'per_page' => 1 ) )->get_headers();

		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
	}

	public function test_the_list_filters_by_role() {
		$this->logged_in_user( 'zoe', 'editor' );
		$this->logged_in_user( 'bob', 'subscriber' );

		$this->acting_as( 'administrator' );

		$data = $this->dispatch( 'GET', '/sessions', array( 'role' => 'editor' ) )->get_data();

		$this->assertSame( array( 'zoe' ), array_column( $data, 'user_login' ) );
	}

	public function test_the_list_searches() {
		$this->logged_in_user( 'zoe' );
		$this->logged_in_user( 'bob' );

		$this->acting_as( 'administrator' );

		$data = $this->dispatch( 'GET', '/sessions', array( 'search' => 'zoe' ) )->get_data();

		$this->assertSame( array( 'zoe' ), array_column( $data, 'user_login' ) );
	}

	/*
	 * Destroying one user.
	 */

	public function test_an_admin_can_force_one_user_out() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'DELETE', '/sessions/' . $victim );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['destroyed'] );
		$this->assertEmpty( get_user_meta( $victim, 'session_tokens', true ) );
	}

	public function test_forcing_out_a_missing_user_reports_false() {
		$this->acting_as( 'administrator' );

		$this->assertFalse( $this->dispatch( 'DELETE', '/sessions/999999' )->get_data()['destroyed'] );
	}

	/*
	 * Destroying one session.
	 */

	public function test_an_admin_can_drop_a_single_device() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'administrator' );

		$token_id = $this->dispatch( 'GET', '/sessions' )->get_data()[0]['sessions'][0]['token_id'];

		$response = $this->dispatch( 'DELETE', '/sessions/' . $victim . '/' . $token_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['destroyed'] );
		$this->assertEmpty( get_user_meta( $victim, 'session_tokens', true ) );
	}

	public function test_dropping_an_unknown_device_reports_false() {
		$victim = $this->logged_in_user( 'zoe' );

		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'DELETE', '/sessions/' . $victim . '/deadbeef' );

		$this->assertFalse( $response->get_data()['destroyed'] );
		$this->assertNotEmpty( get_user_meta( $victim, 'session_tokens', true ) );
	}

	/*
	 * Bulk.
	 */

	public function test_bulk_destroy_reports_the_affected_count() {
		$zoe = $this->logged_in_user( 'zoe' );
		$bob = $this->logged_in_user( 'bob' );

		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'POST', '/sessions/bulk-destroy', array( 'user_ids' => array( $zoe, $bob ) ) );

		$this->assertSame( 2, $response->get_data()['affected'] );
		$this->assertEmpty( get_user_meta( $zoe, 'session_tokens', true ) );
		$this->assertEmpty( get_user_meta( $bob, 'session_tokens', true ) );
	}

	public function test_bulk_destroy_keeps_the_acting_admin_signed_in_by_default() {
		$zoe      = $this->logged_in_user( 'zoe' );
		$admin_id = $this->acting_as( 'administrator' );
		$this->give_session( $admin_id );

		$response = $this->dispatch( 'POST', '/sessions/bulk-destroy', array( 'user_ids' => array( $zoe, $admin_id ) ) );

		$this->assertSame( 1, $response->get_data()['affected'] );
		$this->assertNotEmpty( get_user_meta( $admin_id, 'session_tokens', true ), 'The admin must not sign themselves out.' );
		$this->assertEmpty( get_user_meta( $zoe, 'session_tokens', true ) );
	}

	public function test_bulk_destroy_can_include_the_acting_admin() {
		$admin_id = $this->acting_as( 'administrator' );
		$this->give_session( $admin_id );

		$response = $this->dispatch(
			'POST',
			'/sessions/bulk-destroy',
			array(
				'user_ids'     => array( $admin_id ),
				'exclude_self' => false,
			)
		);

		$this->assertSame( 1, $response->get_data()['affected'] );
		$this->assertEmpty( get_user_meta( $admin_id, 'session_tokens', true ) );
	}

	public function test_bulk_destroy_requires_user_ids() {
		$this->acting_as( 'administrator' );

		$this->assertSame( 400, $this->dispatch( 'POST', '/sessions/bulk-destroy' )->get_status() );
	}

	/*
	 * Destroy all.
	 */

	public function test_destroy_all_signs_everyone_out_but_the_acting_admin() {
		$zoe      = $this->logged_in_user( 'zoe' );
		$bob      = $this->logged_in_user( 'bob' );
		$admin_id = $this->acting_as( 'administrator' );
		$this->give_session( $admin_id );

		$response = $this->dispatch( 'POST', '/sessions/destroy-all' );

		$this->assertSame( 2, $response->get_data()['affected'] );
		$this->assertEmpty( get_user_meta( $zoe, 'session_tokens', true ) );
		$this->assertEmpty( get_user_meta( $bob, 'session_tokens', true ) );
		$this->assertNotEmpty( get_user_meta( $admin_id, 'session_tokens', true ) );
	}

	public function test_destroy_all_can_include_the_acting_admin() {
		$this->logged_in_user( 'zoe' );
		$admin_id = $this->acting_as( 'administrator' );
		$this->give_session( $admin_id );

		$response = $this->dispatch( 'POST', '/sessions/destroy-all', array( 'exclude_self' => false ) );

		$this->assertSame( 2, $response->get_data()['affected'] );
		$this->assertEmpty( get_user_meta( $admin_id, 'session_tokens', true ) );
	}

	/**
	 * Create a user with an active session.
	 *
	 * @param string $login Login name.
	 * @param string $role  Role slug.
	 * @return int The user id.
	 */
	protected function logged_in_user( $login, $role = 'subscriber' ) {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => $login,
				'role'       => $role,
			)
		);

		$this->give_session( $user_id );

		return $user_id;
	}

	/**
	 * Give an existing user a session.
	 *
	 * @param int $user_id User id.
	 * @return string The raw token.
	 */
	protected function give_session( $user_id ) {
		static $nonce = 0;

		return WP_Session_Tokens::get_instance( $user_id )->create( time() + DAY_IN_SECONDS + ( ++$nonce ) );
	}
}

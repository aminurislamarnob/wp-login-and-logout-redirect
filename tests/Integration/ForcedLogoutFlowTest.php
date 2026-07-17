<?php
/**
 * End-to-end forced-logout flow.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

use PluginizeLab\WpLoginLogoutRedirect\Sessions\SessionRepository;
use WP_REST_Request;
use WP_REST_Server;
use WP_Session_Tokens;

/**
 * Real sessions minted through WP_Session_Tokens, listed and destroyed the way
 * the Logged-in Users screen does it, with the audit log wired up behind.
 *
 * The sessions the repository reports and the tokens core actually stored have to
 * stay in agreement — that seam is what these tests hold down.
 *
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Sessions\SessionRepository
 * @covers \PluginizeLab\WpLoginLogoutRedirect\REST\SessionsController
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Logger
 */
class ForcedLogoutFlowTest extends IntegrationTestCase {

	/**
	 * Subject under test.
	 *
	 * @var SessionRepository
	 */
	protected $sessions;

	/**
	 * REST server for the controller half.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->sessions = new SessionRepository();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/*
	 * Listing what core stored.
	 */

	public function test_a_signed_in_user_shows_up_with_a_live_session() {
		$user = $this->make_user( 'editor' );

		$this->create_session( $user->ID );

		$result = $this->sessions->query();

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $user->ID, $result['items'][0]['user_id'] );
		$this->assertSame( 1, $result['items'][0]['session_count'] );
	}

	public function test_each_device_is_listed_as_its_own_session() {
		$user = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		$item = $this->sessions->query()['items'][0];

		$this->assertSame( 3, $item['session_count'] );
		$this->assertCount( 3, $item['sessions'] );
	}

	public function test_every_listed_session_has_a_distinct_id() {
		$user = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		$ids = wp_list_pluck( $this->sessions->query()['items'][0]['sessions'], 'token_id' );

		// Two sessions sharing an id means destroying one destroys the other.
		$this->assertCount( 3, array_unique( $ids ) );
	}

	public function test_an_expired_session_is_not_listed() {
		$user = $this->make_user();

		$this->create_session( $user->ID, time() + DAY_IN_SECONDS );

		// Backdate one of core's own token rows rather than inventing the shape.
		$tokens = get_user_meta( $user->ID, 'session_tokens', true );
		foreach ( $tokens as $verifier => $data ) {
			$tokens[ $verifier ]['expiration'] = time() - HOUR_IN_SECONDS;
		}
		update_user_meta( $user->ID, 'session_tokens', $tokens );

		$this->assertCount( 0, $this->sessions->query()['items'] );
	}

	public function test_a_user_with_no_session_is_not_listed() {
		$this->make_user();

		$this->assertCount( 0, $this->sessions->query()['items'] );
	}

	public function test_the_requesting_admin_can_recognise_their_own_session() {
		$admin = $this->make_user( 'administrator' );

		$this->act_as_session( $admin->ID, $this->create_session( $admin->ID ) );

		$sessions = $this->sessions->query()['items'][0]['sessions'];

		$this->assertTrue( $sessions[0]['is_current'], 'The admin must be able to tell which session is theirs.' );
	}

	public function test_the_admins_other_devices_are_not_flagged_as_current() {
		$admin = $this->make_user( 'administrator' );

		$this->act_as_session( $admin->ID, $this->create_session( $admin->ID ) );
		$this->create_session( $admin->ID );

		$current = array_filter(
			$this->sessions->query()['items'][0]['sessions'],
			function ( $session ) {
				return $session['is_current'];
			}
		);

		$this->assertCount( 1, $current, 'Exactly one of the admin\'s devices is the one they are on.' );
	}

	public function test_another_users_session_is_not_flagged_as_current() {
		$admin = $this->make_user( 'administrator' );
		$other = $this->make_user( 'subscriber' );

		$this->act_as_session( $admin->ID, $this->create_session( $admin->ID ) );
		$this->create_session( $other->ID );

		foreach ( $this->sessions->query()['items'] as $item ) {
			if ( $item['user_id'] === $other->ID ) {
				$this->assertFalse( $item['sessions'][0]['is_current'] );
			}
		}
	}

	/*
	 * Destroying one device.
	 */

	public function test_destroying_one_session_leaves_the_users_other_devices_signed_in() {
		$user = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		$target = $this->sessions->query()['items'][0]['sessions'][0]['token_id'];

		$this->assertTrue( $this->sessions->destroy_session( $user->ID, $target ) );
		$this->assertSame( 2, $this->session_count( $user->ID ), 'Only the targeted device should be signed out.' );
	}

	public function test_the_destroyed_session_is_the_one_that_was_asked_for() {
		$user = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		$before = wp_list_pluck( $this->sessions->query()['items'][0]['sessions'], 'token_id' );

		$this->sessions->destroy_session( $user->ID, $before[0] );

		$after = wp_list_pluck( $this->sessions->query()['items'][0]['sessions'], 'token_id' );

		$this->assertNotContains( $before[0], $after );
		$this->assertContains( $before[1], $after );
	}

	public function test_the_token_of_a_destroyed_session_stops_authenticating() {
		$user = $this->make_user();

		$doomed   = $this->create_session( $user->ID );
		$survivor = $this->create_session( $user->ID );

		$manager = WP_Session_Tokens::get_instance( $user->ID );

		$this->assertTrue( $manager->verify( $doomed ) );

		$target = null;
		foreach ( $this->sessions->query()['items'][0]['sessions'] as $session ) {
			$target = $session['token_id'];

			$this->sessions->destroy_session( $user->ID, $target );

			if ( ! $manager->verify( $doomed ) ) {
				break;
			}
		}

		$this->assertFalse( $manager->verify( $doomed ), 'The destroyed token must no longer authenticate.' );
		$this->assertTrue( $manager->verify( $survivor ), 'The untouched token must still authenticate.' );
	}

	public function test_destroying_an_unknown_session_id_changes_nothing() {
		$user = $this->make_user();

		$this->create_session( $user->ID );

		$this->assertFalse( $this->sessions->destroy_session( $user->ID, 'not-a-real-token-id' ) );
		$this->assertSame( 1, $this->session_count( $user->ID ) );
	}

	public function test_destroying_the_last_session_clears_the_meta_entirely() {
		$user = $this->make_user();

		$this->create_session( $user->ID );

		$target = $this->sessions->query()['items'][0]['sessions'][0]['token_id'];

		$this->sessions->destroy_session( $user->ID, $target );

		$this->assertSame( 0, $this->session_count( $user->ID ) );
	}

	/*
	 * Destroying a whole user / everyone.
	 */

	public function test_destroying_a_user_signs_out_all_of_their_devices() {
		$user = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		$this->assertTrue( $this->sessions->destroy_user( $user->ID ) );
		$this->assertSame( 0, $this->session_count( $user->ID ) );
	}

	public function test_destroying_a_user_leaves_everyone_else_signed_in() {
		$target    = $this->make_user();
		$bystander = $this->make_user();

		$this->create_session( $target->ID );
		$this->create_session( $bystander->ID );

		$this->sessions->destroy_user( $target->ID );

		$this->assertSame( 1, $this->session_count( $bystander->ID ) );
	}

	public function test_destroying_everyone_can_spare_the_acting_admin() {
		$admin = $this->make_user( 'administrator' );
		$one   = $this->make_user();
		$two   = $this->make_user();

		$this->create_session( $admin->ID );
		$this->create_session( $one->ID );
		$this->create_session( $two->ID );

		$affected = $this->sessions->destroy_all( array( $admin->ID ) );

		$this->assertSame( 2, $affected );
		$this->assertSame( 1, $this->session_count( $admin->ID ), 'The admin must not sign themselves out.' );
		$this->assertSame( 0, $this->session_count( $one->ID ) );
		$this->assertSame( 0, $this->session_count( $two->ID ) );
	}

	public function test_a_bulk_destroy_reports_how_many_users_it_hit() {
		$one = $this->make_user();
		$two = $this->make_user();

		$this->create_session( $one->ID );
		$this->create_session( $two->ID );

		$this->assertSame( 2, $this->sessions->destroy_users( array( $one->ID, $two->ID, 999999 ) ) );
	}

	/*
	 * The audit trail behind a forced logout.
	 */

	public function test_a_forced_logout_is_logged_against_the_admin_who_did_it() {
		$this->enable_logs();

		$admin = $this->make_user( 'administrator', array( 'user_login' => 'boss' ) );
		$user  = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->create_session( $user->ID );
		wp_set_current_user( $admin->ID );

		$target = $this->sessions->query()['items'][0]['sessions'][0]['token_id'];
		$this->sessions->destroy_session( $user->ID, $target );

		$row = $this->only_log_row();

		$this->assertSame( 'forced_logout', $row['event'] );
		$this->assertSame( 'ada', $row['username'] );
		$this->assertSame( (string) $user->ID, $row['user_id'] );
		$this->assertStringContainsString( 'boss', $row['description'] );
		$this->assertStringContainsString( 'session', $row['description'] );
	}

	public function test_destroying_a_whole_user_is_logged_with_the_user_scope() {
		$this->enable_logs();

		$user = $this->make_user();
		$this->create_session( $user->ID );

		$this->sessions->destroy_user( $user->ID );

		$this->assertStringContainsString( 'user', $this->only_log_row()['description'] );
	}

	public function test_destroying_everyone_logs_a_row_per_user() {
		$this->enable_logs();

		$one = $this->make_user();
		$two = $this->make_user();

		$this->create_session( $one->ID );
		$this->create_session( $two->ID );

		$this->sessions->destroy_all();

		$this->assertCount( 2, $this->log_rows_for( 'forced_logout' ) );
	}

	public function test_a_forced_logout_is_not_logged_while_logging_is_switched_off() {
		$user = $this->make_user();
		$this->create_session( $user->ID );

		$this->sessions->destroy_user( $user->ID );

		$this->assertCount( 0, $this->all_log_rows() );
	}

	/*
	 * The same flow driven through the REST API, as the admin screen does it.
	 */

	public function test_an_admin_can_sign_out_one_device_over_rest() {
		$this->enable_logs();

		$admin = $this->make_user( 'administrator' );
		$user  = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		wp_set_current_user( $admin->ID );

		$listed = $this->rest( 'GET', '/sessions' )->get_data();
		$target = $listed[0]['sessions'][0]['token_id'];

		$response = $this->rest( 'DELETE', '/sessions/' . $user->ID . '/' . $target );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $this->session_count( $user->ID ) );
		$this->assertCount( 1, $this->log_rows_for( 'forced_logout' ) );
	}

	public function test_an_admin_can_sign_a_user_out_everywhere_over_rest() {
		$admin = $this->make_user( 'administrator' );
		$user  = $this->make_user();

		$this->create_session( $user->ID );
		$this->create_session( $user->ID );

		wp_set_current_user( $admin->ID );

		$this->assertSame( 200, $this->rest( 'DELETE', '/sessions/' . $user->ID )->get_status() );
		$this->assertSame( 0, $this->session_count( $user->ID ) );
	}

	public function test_a_subscriber_cannot_sign_anyone_out_over_rest() {
		$victim      = $this->make_user();
		$subscriber  = $this->make_user( 'subscriber' );

		$this->create_session( $victim->ID );

		wp_set_current_user( $subscriber->ID );

		$this->assertSame( 403, $this->rest( 'DELETE', '/sessions/' . $victim->ID )->get_status() );
		$this->assertSame( 1, $this->session_count( $victim->ID ), 'The session must survive an unauthorized request.' );
	}

	/**
	 * Dispatch against the plugin's REST namespace.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /wplalr/v1.
	 * @param array  $params Params.
	 * @return \WP_REST_Response
	 */
	protected function rest( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/wplalr/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}
}

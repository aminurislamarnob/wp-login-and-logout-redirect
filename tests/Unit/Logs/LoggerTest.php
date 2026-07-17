<?php
/**
 * Audit-log event recording tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Logs;

use PluginizeLab\WpLoginLogoutRedirect\Logs\Logger;
use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Logger
 */
class LoggerTest extends TestCase {

	/**
	 * Data layer.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->repository = new LogRepository();
	}

	/*
	 * The enable gate.
	 */

	public function test_logging_is_off_by_default() {
		$logger = new Logger( $this->repository );

		$this->assertFalse( has_action( 'wp_login', array( $logger, 'on_login' ) ) );
		$this->assertFalse( has_action( 'wp_login_failed', array( $logger, 'on_login_failed' ) ) );
	}

	public function test_hooks_are_registered_once_enabled() {
		$logger = $this->enabled_logger();

		$this->assertNotFalse( has_action( 'wp_login', array( $logger, 'on_login' ) ) );
		$this->assertNotFalse( has_action( 'wplalr_after_resolve', array( $logger, 'on_after_resolve' ) ) );
		$this->assertNotFalse( has_action( 'wplalr_redirect_resolved', array( $logger, 'on_redirect_resolved' ) ) );
		$this->assertNotFalse( has_action( 'wp_login_failed', array( $logger, 'on_login_failed' ) ) );
		$this->assertNotFalse( has_action( 'shutdown', array( $logger, 'flush_pending' ) ) );
		$this->assertNotFalse( has_action( 'wplalr_session_destroyed', array( $logger, 'on_session_destroyed' ) ) );
	}

	public function test_a_disabled_logger_records_nothing_on_a_real_login() {
		new Logger( $this->repository );

		$user = $this->make_user( 'zoe' );
		do_action( 'wp_login', $user->user_login, $user );

		$this->assertCount( 0, $this->all_log_rows() );
	}

	/*
	 * Login.
	 */

	public function test_a_login_is_parked_until_its_redirect_resolves() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_login( $user->user_login, $user );

		$this->assertCount( 0, $this->all_log_rows(), 'The row must wait for the resolved URL.' );

		$logger->on_after_resolve( 'https://example.test/rule-url/', 'login', $user, array( 'id' => 'rule-7' ) );
		$logger->on_redirect_resolved( 'https://example.test/hi/', 'login' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'login', $rows[0]['event'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
		$this->assertSame( (string) $user->ID, (string) $rows[0]['user_id'] );
		$this->assertSame( 'https://example.test/hi/', $rows[0]['redirect_url'] );
		$this->assertSame( 'rule-7', $rows[0]['rule_id'] );
		$this->assertSame( 'success', $rows[0]['status'] );
	}

	public function test_the_logged_url_is_the_final_destination_not_the_rule_url() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_login( $user->user_login, $user );

		// What the rule engine resolved: empty, because no rule matched.
		$logger->on_after_resolve( '', 'login', $user, null );

		// Where Redirection actually settled on sending them.
		$logger->on_redirect_resolved( 'https://example.test/members/', 'login' );

		$this->assertSame( 'https://example.test/members/', $this->all_log_rows()[0]['redirect_url'] );
	}

	public function test_a_login_that_matched_no_rule_records_an_empty_rule_id() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_login( $user->user_login, $user );
		$logger->on_after_resolve( '', 'login', $user, null );
		$logger->on_redirect_resolved( 'https://example.test/hi/', 'login' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertNull( $rows[0]['rule_id'] );
	}

	public function test_the_pending_login_is_only_written_once() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_login( $user->user_login, $user );
		$logger->on_after_resolve( 'https://example.test/hi/', 'login', $user, null );
		$logger->on_redirect_resolved( 'https://example.test/hi/', 'login' );

		// The shutdown safety net must not duplicate the row.
		$logger->flush_pending();

		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_shutdown_flushes_a_login_that_never_resolved_a_redirect() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		// A programmatic login that never hits the login_redirect filter.
		$logger->on_login( $user->user_login, $user );
		$logger->flush_pending_login();

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'login', $rows[0]['event'] );
	}

	public function test_flushing_with_nothing_pending_is_a_noop() {
		$logger = $this->enabled_logger();

		$logger->flush_pending_login();

		$this->assertCount( 0, $this->all_log_rows() );
	}

	public function test_a_login_without_a_user_object_records_a_zero_user_id() {
		$logger = $this->enabled_logger();

		$logger->on_login( 'ghost', null );
		$logger->flush_pending_login();

		$rows = $this->all_log_rows();

		$this->assertSame( 'ghost', $rows[0]['username'] );
		$this->assertSame( '0', (string) $rows[0]['user_id'] );
	}

	public function test_a_real_login_is_recorded_end_to_end() {
		$logger = $this->enabled_logger();

		$user = $this->make_user( 'zoe' );

		// The shutdown action itself is not fired here — it tears down PHPUnit's
		// output buffers. The hook wiring is asserted separately above.
		do_action( 'wp_login', $user->user_login, $user );
		$logger->flush_pending_login();

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'login', $rows[0]['event'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
	}

	/*
	 * Logout.
	 */

	public function test_a_logout_is_recorded_when_its_redirect_resolves() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_after_resolve( 'https://example.test/rule-url/', 'logout', $user, array( 'id' => 'rule-9' ) );
		$logger->on_redirect_resolved( 'https://example.test/bye/', 'logout' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'logout', $rows[0]['event'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
		$this->assertSame( 'https://example.test/bye/', $rows[0]['redirect_url'] );
		$this->assertSame( 'rule-9', $rows[0]['rule_id'] );
	}

	public function test_a_logout_without_a_user_still_records() {
		$logger = $this->enabled_logger();

		$logger->on_after_resolve( '', 'logout', null, null );
		$logger->on_redirect_resolved( 'https://example.test/bye/', 'logout' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( '', $rows[0]['username'] );
	}

	public function test_a_logout_that_never_resolved_a_redirect_is_flushed_at_shutdown() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_after_resolve( '', 'logout', $user, null );

		$this->assertCount( 0, $this->all_log_rows(), 'The row should still be parked.' );

		$logger->flush_pending();

		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_a_logout_does_not_consume_a_pending_login() {
		$logger = $this->enabled_logger();
		$user   = $this->make_user( 'zoe' );

		$logger->on_login( $user->user_login, $user );
		$logger->on_after_resolve( '', 'logout', $user, null );
		$logger->on_redirect_resolved( 'https://example.test/bye/', 'logout' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'logout', $rows[0]['event'], 'The logout must not flush the parked login.' );
	}

	/*
	 * Failed logins.
	 */

	public function test_a_failed_login_records_the_error_code() {
		$logger = $this->enabled_logger();

		$logger->on_login_failed( 'zoe', new \WP_Error( 'incorrect_password', 'Nope' ) );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'failed', $rows[0]['event'] );
		$this->assertSame( 'failed', $rows[0]['status'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
		$this->assertSame( 'incorrect_password', $rows[0]['error_code'] );
	}

	public function test_a_failed_login_without_an_error_object_still_records() {
		$logger = $this->enabled_logger();

		$logger->on_login_failed( 'zoe', null );

		$rows = $this->all_log_rows();

		$this->assertSame( 'failed', $rows[0]['event'] );
		$this->assertSame( '', $rows[0]['error_code'] );
	}

	/*
	 * Forced logout.
	 */

	public function test_a_forced_logout_records_who_did_it() {
		$logger = $this->enabled_logger();

		$admin  = $this->make_user( 'boss', 'administrator' );
		$victim = $this->make_user( 'zoe' );

		wp_set_current_user( $admin->ID );

		$logger->on_session_destroyed( $victim->ID, 'bulk' );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'forced_logout', $rows[0]['event'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
		$this->assertSame( (string) $victim->ID, (string) $rows[0]['user_id'] );
		$this->assertStringContainsString( 'boss', $rows[0]['description'] );
		$this->assertStringContainsString( 'bulk', $rows[0]['description'] );
	}

	public function test_a_forced_logout_with_no_actor_is_attributed_to_the_system() {
		$logger = $this->enabled_logger();
		$victim = $this->make_user( 'zoe' );

		wp_set_current_user( 0 );

		$logger->on_session_destroyed( $victim->ID, 'all' );

		$rows = $this->all_log_rows();

		$this->assertStringContainsString( 'system', $rows[0]['description'] );
	}

	public function test_the_session_destroyed_action_records_a_row() {
		$this->enabled_logger();
		$victim = $this->make_user( 'zoe' );

		do_action( 'wplalr_session_destroyed', $victim->ID, 'session' );

		$this->assertCount( 1, $this->all_log_rows() );
	}

	/*
	 * Request context + extension hooks.
	 */

	public function test_the_request_ip_and_agent_are_captured() {
		$original_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$original_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;

		$_SERVER['REMOTE_ADDR']     = '203.0.113.9';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$logger = $this->enabled_logger();
		$logger->on_login_failed( 'zoe', null );

		$rows = $this->all_log_rows();

		$this->assertSame( '203.0.113.9', $rows[0]['ip'] );
		$this->assertSame( 'Chrome', $rows[0]['browser'] );
		$this->assertSame( 'Windows', $rows[0]['device_os'] );

		$this->restore_server( 'REMOTE_ADDR', $original_ip );
		$this->restore_server( 'HTTP_USER_AGENT', $original_ua );
	}

	public function test_the_recorded_ip_is_filterable_for_sites_behind_a_proxy() {
		add_filter(
			'wplalr_log_client_ip',
			function () {
				return '198.51.100.7';
			}
		);

		$logger = $this->enabled_logger();
		$logger->on_login_failed( 'zoe', null );

		$this->assertSame( '198.51.100.7', $this->all_log_rows()[0]['ip'] );
	}

	public function test_the_log_recorded_action_fires_with_the_row_id() {
		$logger = $this->enabled_logger();

		$captured = array();
		add_action(
			'wplalr_log_recorded',
			function ( $row_id, $data ) use ( &$captured ) {
				$captured[] = array( $row_id, $data['event'] );
			},
			10,
			2
		);

		$logger->on_login_failed( 'zoe', null );

		$this->assertCount( 1, $captured );
		$this->assertGreaterThan( 0, $captured[0][0] );
		$this->assertSame( 'failed', $captured[0][1] );
	}

	/**
	 * Turn logging on and return a freshly hooked logger.
	 *
	 * @return Logger
	 */
	protected function enabled_logger() {
		update_option( 'wplalr_enable_logs', 'yes' );

		return new Logger( $this->repository );
	}

	/**
	 * Create a user and return the WP_User.
	 *
	 * @param string $login Login name.
	 * @param string $role  Role slug.
	 * @return \WP_User
	 */
	protected function make_user( $login, $role = 'subscriber' ) {
		return get_user_by(
			'id',
			self::factory()->user->create(
				array(
					'user_login' => $login,
					'role'       => $role,
				)
			)
		);
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

<?php
/**
 * Shared base for integration tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use PluginizeLab\WpLoginLogoutRedirect\Logs\Logger;
use PluginizeLab\WpLoginLogoutRedirect\Logs\Notifier;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;
use WP_Session_Tokens;

/**
 * Drives the plugin through its real WordPress hooks.
 *
 * Unlike the unit suite, these tests call no plugin method directly: they fire
 * `wp_login` / `login_redirect` / `wp_logout` and assert on what the whole object
 * graph did. The plugin bootstraps itself during the test bootstrap, so
 * Redirection and UserLoginTime are already hooked and are used as-is —
 * constructing a second instance would double every callback.
 *
 * Logger and Notifier are the exception. Both gate on `wplalr_enable_logs` in
 * their constructor, and logging is off when the plugin boots, so they hook
 * nothing. enable_logs() switches the option on and builds the pair the way
 * init_classes() does, which is the only way to exercise the logging path.
 */
abstract class IntegrationTestCase extends TestCase {

	/**
	 * Logger built by enable_logs(), if any.
	 *
	 * @var Logger|null
	 */
	protected $logger = null;

	/**
	 * Notifier built by enable_logs(), if any.
	 *
	 * @var Notifier|null
	 */
	protected $notifier = null;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// base_row() reads these off $_SERVER, so pin them for assertable output.
		$_SERVER['REMOTE_ADDR']     = '203.0.113.10';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0 Safari/537.36';

		// A cookie left by act_as_session() would make the next test think it is
		// still on that device.
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

		reset_phpmailer_instance();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		// Unhook the pair enable_logs() built so a later test cannot log twice,
		// rather than trusting the hook backup to be restored.
		if ( $this->logger instanceof Logger ) {
			remove_action( 'wp_login', array( $this->logger, 'on_login' ), 20 );
			remove_action( 'wplalr_after_resolve', array( $this->logger, 'on_after_resolve' ), 10 );
			remove_action( 'wp_login_failed', array( $this->logger, 'on_login_failed' ), 10 );
			remove_action( 'shutdown', array( $this->logger, 'flush_pending_login' ) );
			remove_action( 'wplalr_session_destroyed', array( $this->logger, 'on_session_destroyed' ), 10 );
		}

		if ( $this->notifier instanceof Notifier ) {
			remove_action( 'wplalr_log_recorded', array( $this->notifier, 'maybe_queue_login_alert' ), 10 );
			remove_action( Notifier::ALERT_HOOK, array( $this->notifier, 'send_login_alert' ), 10 );
			remove_action( Notifier::DIGEST_HOOK, array( $this->notifier, 'send_digest' ) );
		}

		$this->logger   = null;
		$this->notifier = null;

		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Switch logging on and wire up the classes that depend on it.
	 *
	 * Order matters: both constructors read the option, so it has to be set first.
	 *
	 * @return Logger
	 */
	protected function enable_logs() {
		update_option( 'wplalr_enable_logs', 'yes' );

		$repository     = new LogRepository();
		$this->logger   = new Logger( $repository );
		$this->notifier = new Notifier( $repository );

		return $this->logger;
	}

	/**
	 * Create a user.
	 *
	 * @param string $role  Role slug.
	 * @param array  $args  Extra user args.
	 * @return \WP_User
	 */
	protected function make_user( $role = 'subscriber', array $args = array() ) {
		$user_id = self::factory()->user->create(
			array_merge(
				array(
					'role'      => $role,
					'user_pass' => 'correct-horse',
				),
				$args
			)
		);

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Sign a user in for real and return where they would be sent.
	 *
	 * wp_signon() fires `wp_login` but never resolves a destination — wp-login.php
	 * applies `login_redirect` separately, once authentication has succeeded. Both
	 * halves are replayed here so the whole login path runs in order.
	 *
	 * @param \WP_User $user     The user to sign in.
	 * @param string   $password Plain-text password.
	 * @param string   $default  The redirect wp-login.php would pass in.
	 * @return string The resolved redirect URL.
	 */
	protected function sign_in( $user, $password = 'correct-horse', $default = '' ) {
		$signed_in = wp_signon(
			array(
				'user_login'    => $user->user_login,
				'user_password' => $password,
				'remember'      => false,
			)
		);

		$this->assertNotWPError( $signed_in, 'The user should have been able to sign in.' );

		wp_set_current_user( $signed_in->ID );

		return apply_filters( 'login_redirect', $default, $default, $signed_in );
	}

	/**
	 * Attempt a sign-in that is expected to fail.
	 *
	 * @param string $login    Username.
	 * @param string $password Wrong password.
	 * @return \WP_Error
	 */
	protected function fail_sign_in( $login, $password = 'wrong-password' ) {
		$result = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
			)
		);

		$this->assertWPError( $result, 'The sign-in was supposed to fail.' );

		return $result;
	}

	/**
	 * Fire wp_logout and capture the URL Redirection sends the user to.
	 *
	 * redirect_after_logout() ends in exit(), so the redirect is intercepted at the
	 * `wp_redirect` filter and unwound with an exception.
	 *
	 * @param int $user_id The user logging out.
	 * @return string The captured redirect URL.
	 */
	protected function log_out( $user_id ) {
		$captured = '';

		$catch = function ( $location ) use ( &$captured ) {
			$captured = $location;

			throw new RedirectCaught( $location );
		};

		add_filter( 'wp_redirect', $catch, 99 );

		try {
			do_action( 'wp_logout', $user_id );

			$this->fail( 'wp_logout should have redirected.' );
		} catch ( RedirectCaught $e ) {
			$captured = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $catch, 99 );
		}

		return $captured;
	}

	/**
	 * Give a user a live session and return its raw token.
	 *
	 * @param int $user_id    User id.
	 * @param int $expiration Absolute expiry timestamp.
	 * @return string The raw session token.
	 */
	protected function create_session( $user_id, $expiration = 0 ) {
		$expiration = $expiration ? $expiration : time() + DAY_IN_SECONDS;

		return WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
	}

	/**
	 * Become a user on one specific device.
	 *
	 * current_token_id() reaches the token through wp_get_session_token(), which
	 * only ever reads the logged_in cookie — there is no filter on it. So the
	 * cookie is minted for real, exactly as wp_set_auth_cookie() would.
	 *
	 * @param int    $user_id User id.
	 * @param string $token   Raw session token from create_session().
	 * @return void
	 */
	protected function act_as_session( $user_id, $token ) {
		wp_set_current_user( $user_id );

		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie(
			$user_id,
			time() + DAY_IN_SECONDS,
			'logged_in',
			$token
		);
	}

	/**
	 * Count a user's stored session tokens.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	protected function session_count( $user_id ) {
		$sessions = get_user_meta( $user_id, 'session_tokens', true );

		return is_array( $sessions ) ? count( $sessions ) : 0;
	}

	/**
	 * The single log row, asserting there is exactly one.
	 *
	 * @return array
	 */
	protected function only_log_row() {
		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows, 'Expected exactly one log row.' );

		return $rows[0];
	}

	/**
	 * Log rows of a given event.
	 *
	 * @param string $event Event name.
	 * @return array
	 */
	protected function log_rows_for( $event ) {
		return array_values(
			array_filter(
				$this->all_log_rows(),
				function ( $row ) use ( $event ) {
					return $row['event'] === $event;
				}
			)
		);
	}

	/**
	 * The most recently sent email, or null.
	 *
	 * @return object|null
	 */
	protected function last_mail() {
		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();

		return $sent ? $sent : null;
	}
}

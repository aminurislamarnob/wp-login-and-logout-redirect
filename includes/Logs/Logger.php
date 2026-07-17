<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records login / logout / failed-login events to the audit-log table.
 *
 * Gated behind the `wplalr_enable_logs` option (off by default) so no PII is
 * collected unless an admin opts in.
 *
 * Redirect capture: `wp_login` fires before WordPress resolves the login
 * redirect, so a login is parked as "pending" on `wp_login`, tagged with the
 * matched rule id from `wplalr_after_resolve`, and flushed from
 * `wplalr_redirect_resolved` once the destination is final. The two hooks are
 * both needed: only the first knows which rule matched, and only the second
 * knows where the user is actually going — `wplalr_after_resolve` fires inside
 * the rule engine, before placeholders, fallbacks and validation run. A
 * `shutdown` safety net flushes the row even when no redirect was resolved
 * (e.g. a programmatic login that never hit the `login_redirect` filter).
 */
class Logger {

	/**
	 * Data layer.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Pending login row awaiting redirect enrichment, or null.
	 *
	 * @var array|null
	 */
	protected $pending_login = null;

	/**
	 * Pending logout row awaiting redirect enrichment, or null.
	 *
	 * @var array|null
	 */
	protected $pending_logout = null;

	/**
	 * The constructor.
	 *
	 * @param LogRepository $repository Data layer.
	 */
	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'wp_login', array( $this, 'on_login' ), 20, 2 );
		add_action( 'wplalr_after_resolve', array( $this, 'on_after_resolve' ), 10, 4 );
		add_action( 'wplalr_redirect_resolved', array( $this, 'on_redirect_resolved' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush_pending' ) );
		add_action( 'wplalr_session_destroyed', array( $this, 'on_session_destroyed' ), 10, 2 );
	}

	/**
	 * Whether logging is switched on.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return 'yes' === get_option( 'wplalr_enable_logs', 'no' );
	}

	/**
	 * Park a login until its redirect is resolved.
	 *
	 * @param string         $user_login The user login name.
	 * @param \WP_User|mixed $user       The logged-in user.
	 * @return void
	 */
	public function on_login( $user_login, $user = null ) {
		$user_id = $user instanceof \WP_User ? $user->ID : 0;

		$this->pending_login = array_merge(
			$this->base_row(),
			array(
				'user_id'  => $user_id,
				'username' => $user_login,
				'event'    => 'login',
				'status'   => 'success',
			)
		);
	}

	/**
	 * Note which rule matched, and park a logout until its destination is final.
	 *
	 * The URL passed here is deliberately ignored: it is the rule engine's own
	 * output, which is empty whenever the global option supplies the destination
	 * and still holds unexpanded `{{placeholders}}` when a rule matched. The row is
	 * written from on_redirect_resolved() instead.
	 *
	 * @param string        $url     Resolved rule URL (unused; see above).
	 * @param string        $event   'login' or 'logout'.
	 * @param \WP_User|null $user    The user being redirected.
	 * @param array|null    $matched The matched rule, or null.
	 * @return void
	 */
	public function on_after_resolve( $url, $event, $user = null, $matched = null ) {
		$rule_id = is_array( $matched ) && ! empty( $matched['id'] ) ? $matched['id'] : '';

		if ( 'login' === $event ) {
			if ( null !== $this->pending_login ) {
				$this->pending_login['rule_id'] = $rule_id;
			}

			return;
		}

		if ( 'logout' === $event ) {
			$user_obj = $user instanceof \WP_User ? $user : null;

			$this->pending_logout = array_merge(
				$this->base_row(),
				array(
					'user_id'  => $user_obj ? $user_obj->ID : 0,
					'username' => $user_obj ? $user_obj->user_login : '',
					'event'    => 'logout',
					'status'   => 'success',
					'rule_id'  => $rule_id,
				)
			);
		}
	}

	/**
	 * Write the parked row now that the destination is settled.
	 *
	 * The hook also passes the user, but the parked row already carries it.
	 *
	 * @param string $url   The final destination URL.
	 * @param string $event 'login' or 'logout'.
	 * @return void
	 */
	public function on_redirect_resolved( $url, $event ) {
		if ( 'login' === $event && null !== $this->pending_login ) {
			$this->pending_login['redirect_url'] = $url;
			$this->flush_pending_login();

			return;
		}

		if ( 'logout' === $event && null !== $this->pending_logout ) {
			$this->pending_logout['redirect_url'] = $url;
			$this->flush_pending_logout();
		}
	}

	/**
	 * Record a failed login attempt.
	 *
	 * @param string         $username The attempted username.
	 * @param \WP_Error|null $error    The authentication error, if any.
	 * @return void
	 */
	public function on_login_failed( $username, $error = null ) {
		$error_code = $error instanceof \WP_Error ? $error->get_error_code() : '';

		$this->record(
			array_merge(
				$this->base_row(),
				array(
					'username'   => $username,
					'event'      => 'failed',
					'status'     => 'failed',
					'error_code' => $error_code,
				)
			)
		);
	}

	/**
	 * Record a forced logout performed from the Logged-in Users screen.
	 *
	 * @param int    $user_id The user whose session(s) were destroyed.
	 * @param string $context Scope: session|user|bulk|all.
	 * @return void
	 */
	public function on_session_destroyed( $user_id, $context = '' ) {
		$user  = get_userdata( $user_id );
		$actor = wp_get_current_user();

		$this->record(
			array_merge(
				$this->base_row(),
				array(
					'user_id'     => $user_id,
					'username'    => $user instanceof \WP_User ? $user->user_login : '',
					'event'       => 'forced_logout',
					'status'      => 'success',
					'description' => sprintf(
						/* translators: 1: admin username, 2: scope (session/user/bulk/all). */
						__( 'Forced logout by %1$s (%2$s)', 'wp-login-logout-redirect' ),
						$actor instanceof \WP_User && $actor->exists() ? $actor->user_login : __( 'system', 'wp-login-logout-redirect' ),
						$context
					),
				)
			)
		);
	}

	/**
	 * Write any row still parked (shutdown safety net for a login or logout that
	 * never resolved a redirect).
	 *
	 * @return void
	 */
	public function flush_pending() {
		$this->flush_pending_login();
		$this->flush_pending_logout();
	}

	/**
	 * Write the parked login row if one is still pending.
	 *
	 * @return void
	 */
	public function flush_pending_login() {
		if ( null === $this->pending_login ) {
			return;
		}

		$row                 = $this->pending_login;
		$this->pending_login = null;

		$this->record( $row );
	}

	/**
	 * Write the parked logout row if one is still pending.
	 *
	 * @return void
	 */
	public function flush_pending_logout() {
		if ( null === $this->pending_logout ) {
			return;
		}

		$row                  = $this->pending_logout;
		$this->pending_logout = null;

		$this->record( $row );
	}

	/**
	 * Insert a row and fire the extension hook.
	 *
	 * @param array $data Row data.
	 * @return void
	 */
	protected function record( array $data ) {
		$row_id = $this->repository->insert( $data );

		if ( $row_id ) {
			/**
			 * Fires after an audit-log row is recorded.
			 *
			 * The Notifier and Pro enrichment (geo-IP) hook this.
			 *
			 * @param int   $row_id The inserted row id.
			 * @param array $data   The row data.
			 */
			do_action( 'wplalr_log_recorded', $row_id, $data );
		}
	}

	/**
	 * Common request context (IP + user agent) for a row.
	 *
	 * @return array
	 */
	protected function base_row() {
		$agent  = $this->user_agent();
		$parsed = UserAgent::parse( $agent );

		return array(
			'ip'        => $this->client_ip(),
			'agent'     => $agent,
			'browser'   => $parsed['browser'],
			'device_os' => $parsed['device_os'],
		);
	}

	/**
	 * Client IP for the current request.
	 *
	 * Uses REMOTE_ADDR only; proxy headers are spoofable, so sites behind a
	 * trusted proxy can override via the filter.
	 *
	 * @return string
	 */
	protected function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client IP recorded on a log row.
		 *
		 * @param string $ip The IP from REMOTE_ADDR.
		 */
		return (string) apply_filters( 'wplalr_log_client_ip', $ip );
	}

	/**
	 * User-agent string for the current request.
	 *
	 * @return string
	 */
	protected function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}
}

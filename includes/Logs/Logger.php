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
 * redirect, so a login is parked as "pending" on `wp_login` and flushed (with
 * the resolved URL + matched rule id) from `wplalr_after_resolve`. A `shutdown`
 * safety net flushes the row even when no redirect was resolved (e.g. a
 * programmatic login that never hit the `login_redirect` filter).
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
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush_pending_login' ) );
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
	 * Enrich + write the pending login (or write a logout row) once the rule
	 * engine has resolved a destination.
	 *
	 * @param string        $url     Resolved URL (may be empty when no rule matched).
	 * @param string        $event   'login' or 'logout'.
	 * @param \WP_User|null  $user    The user being redirected.
	 * @param array|null     $matched The matched rule, or null.
	 * @return void
	 */
	public function on_after_resolve( $url, $event, $user = null, $matched = null ) {
		$rule_id = is_array( $matched ) && ! empty( $matched['id'] ) ? $matched['id'] : '';

		if ( 'login' === $event && null !== $this->pending_login ) {
			$this->pending_login['redirect_url'] = $url;
			$this->pending_login['rule_id']      = $rule_id;
			$this->flush_pending_login();

			return;
		}

		if ( 'logout' === $event ) {
			$user_obj = $user instanceof \WP_User ? $user : null;

			$this->record(
				array_merge(
					$this->base_row(),
					array(
						'user_id'      => $user_obj ? $user_obj->ID : 0,
						'username'     => $user_obj ? $user_obj->user_login : '',
						'event'        => 'logout',
						'status'       => 'success',
						'redirect_url' => $url,
						'rule_id'      => $rule_id,
					)
				)
			);
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
	 * Write the parked login row if one is still pending (shutdown safety net
	 * for logins that never resolved a redirect).
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

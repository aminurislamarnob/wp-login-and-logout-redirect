<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends audit-log email notifications.
 *
 * Two independent features sharing one class, both off by default:
 *
 * 1. Per-event alerts — emails the configured recipient when a user whose role
 *    is in `wplalr_logs_notify_roles` logs in successfully. The send is
 *    deferred to a single cron event so logging in never blocks on SMTP.
 * 2. Digest — a scheduled roll-up of login activity (daily/weekly/monthly),
 *    driven by the `wplalr_logs_digest_send` cron event registered in Installer.
 *
 * Hangs entirely off the existing `wplalr_log_recorded` action; it never touches
 * the write path. Like Logger, it hooks nothing unless logging is enabled.
 */
class Notifier {

	/**
	 * Data layer.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Async single-event hook for per-event alerts.
	 *
	 * @var string
	 */
	const ALERT_HOOK = 'wplalr_send_login_alert';

	/**
	 * Recurring digest hook (scheduled in Installer).
	 *
	 * @var string
	 */
	const DIGEST_HOOK = 'wplalr_logs_digest_send';

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

		add_action( 'wplalr_log_recorded', array( $this, 'maybe_queue_login_alert' ), 10, 2 );
		add_action( self::ALERT_HOOK, array( $this, 'send_login_alert' ), 10, 1 );
		add_action( self::DIGEST_HOOK, array( $this, 'send_digest' ) );
	}

	/**
	 * Whether logging is switched on (same guard as Logger).
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return 'yes' === get_option( 'wplalr_enable_logs', 'no' );
	}

	/**
	 * Queue an async alert when a watched role logs in.
	 *
	 * @param int   $row_id The inserted row id.
	 * @param array $data   The row data.
	 * @return void
	 */
	public function maybe_queue_login_alert( $row_id, $data ) {
		if ( ! isset( $data['event'] ) || 'login' !== $data['event'] ) {
			return;
		}

		$notify_roles = array_values( (array) get_option( 'wplalr_logs_notify_roles', array() ) );

		if ( empty( $notify_roles ) ) {
			return;
		}

		$user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

		if ( ! $user_id ) {
			return;
		}

		// $data carries no roles, so look the user up.
		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		if ( empty( array_intersect( (array) $user->roles, $notify_roles ) ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::ALERT_HOOK, array( (int) $row_id ) );
	}

	/**
	 * Cron handler: re-read the row and email the alert.
	 *
	 * @param int $row_id The log row id.
	 * @return void
	 */
	public function send_login_alert( $row_id ) {
		$row = $this->repository->get( $row_id );

		if ( null === $row ) {
			return;
		}

		$user = $row['user_id'] ? get_userdata( $row['user_id'] ) : false;
		$name = $user instanceof \WP_User ? $user->display_name : $row['username'];

		$subject = sprintf(
			/* translators: 1: site name, 2: username. */
			__( '[%1$s] Login alert: %2$s signed in', 'wp-login-logout-redirect' ),
			wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			$name
		);

		$lines = array(
			sprintf(
				/* translators: %s: user display name / username. */
				__( '%s just signed in to your site.', 'wp-login-logout-redirect' ),
				$name
			),
			'',
			sprintf( '%s: %s', __( 'Username', 'wp-login-logout-redirect' ), $row['username'] ),
			sprintf( '%s: %s', __( 'Time', 'wp-login-logout-redirect' ), $row['created_at'] ),
			sprintf( '%s: %s', __( 'IP address', 'wp-login-logout-redirect' ), $row['ip'] ),
			sprintf( '%s: %s', __( 'Browser', 'wp-login-logout-redirect' ), trim( $row['browser'] . ' / ' . $row['device_os'], ' /' ) ),
		);

		$this->send(
			'wplalr_log_notification_email',
			array(
				'to'      => $this->recipient(),
				'subject' => $subject,
				'body'    => $this->html_body( $subject, $lines ),
				'headers' => $this->headers(),
			),
			array(
				'row'  => $row,
				'user' => $user,
			)
		);
	}

	/**
	 * Cron handler: build and send the activity digest.
	 *
	 * @return void
	 */
	public function send_digest() {
		$cadence = (string) get_option( 'wplalr_logs_digest', '' );

		if ( '' === $cadence ) {
			return;
		}

		$days  = $this->cadence_days( $cadence );
		$stats = $this->repository->stats( $days );

		$window_label = array(
			'daily'   => __( 'last 24 hours', 'wp-login-logout-redirect' ),
			'weekly'  => __( 'last 7 days', 'wp-login-logout-redirect' ),
			'monthly' => __( 'last 30 days', 'wp-login-logout-redirect' ),
		);
		$window = isset( $window_label[ $cadence ] ) ? $window_label[ $cadence ] : '';

		$subject = sprintf(
			/* translators: 1: site name, 2: cadence (Daily/Weekly/Monthly). */
			__( '[%1$s] %2$s login activity digest', 'wp-login-logout-redirect' ),
			wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			ucfirst( $cadence )
		);

		$lines = array(
			sprintf(
				/* translators: %s: time window, e.g. "last 7 days". */
				__( 'Login activity for the %s:', 'wp-login-logout-redirect' ),
				$window
			),
			'',
			sprintf( '%s: %d', __( 'Successful logins', 'wp-login-logout-redirect' ), $stats['login'] ),
			sprintf( '%s: %d', __( 'Logouts', 'wp-login-logout-redirect' ), $stats['logout'] ),
			sprintf( '%s: %d', __( 'Failed logins', 'wp-login-logout-redirect' ), $stats['failed'] ),
			sprintf( '%s: %d', __( 'Total events', 'wp-login-logout-redirect' ), $stats['total'] ),
		);

		$this->send(
			'wplalr_log_digest_email',
			array(
				'to'      => $this->recipient(),
				'subject' => $subject,
				'body'    => $this->html_body( $subject, $lines ),
				'headers' => $this->headers(),
			),
			array(
				'cadence' => $cadence,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Filter the email payload and dispatch it via wp_mail.
	 *
	 * @param string $filter  Filter name for the payload.
	 * @param array  $email   [ to, subject, body, headers ].
	 * @param array  $context Extra context passed to the filter.
	 * @return void
	 */
	protected function send( $filter, array $email, array $context = array() ) {
		/**
		 * Filter an outgoing notification email before it is sent.
		 *
		 * @param array $email   [ 'to', 'subject', 'body', 'headers' ].
		 * @param array $context Extra context (row/user, or cadence/stats).
		 */
		$email = apply_filters( $filter, $email, $context );

		if ( empty( $email['to'] ) ) {
			return;
		}

		wp_mail( $email['to'], $email['subject'], $email['body'], $email['headers'] );
	}

	/**
	 * Resolve the recipient address.
	 *
	 * @return string
	 */
	protected function recipient() {
		$email = (string) get_option( 'wplalr_logs_notification_email', '' );

		return '' !== $email ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * HTML email headers.
	 *
	 * @return array
	 */
	protected function headers() {
		return array( 'Content-Type: text/html; charset=UTF-8' );
	}

	/**
	 * Days window for a digest cadence.
	 *
	 * @param string $cadence daily|weekly|monthly.
	 * @return int
	 */
	protected function cadence_days( $cadence ) {
		switch ( $cadence ) {
			case 'weekly':
				return 7;
			case 'monthly':
				return 30;
			default:
				return 1;
		}
	}

	/**
	 * Wrap body lines in a minimal HTML document.
	 *
	 * @param string   $heading Email heading.
	 * @param string[] $lines   Body lines (blank string = paragraph break).
	 * @return string
	 */
	protected function html_body( $heading, array $lines ) {
		$body = '';

		foreach ( $lines as $line ) {
			$body .= '' === $line ? '<br>' : '<p style="margin:0 0 6px;">' . esc_html( $line ) . '</p>';
		}

		return sprintf(
			'<div style="font-family:sans-serif;font-size:14px;color:#1e1e1e;">'
			. '<h2 style="font-size:16px;">%1$s</h2>%2$s'
			. '<hr style="border:none;border-top:1px solid #ddd;margin:16px 0;">'
			. '<p style="font-size:12px;color:#757575;">%3$s</p></div>',
			esc_html( $heading ),
			$body,
			esc_html( sprintf( /* translators: %s: site name. */ __( 'Sent by WP Login and Logout Redirect on %s.', 'wp-login-logout-redirect' ), get_bloginfo( 'name' ) ) )
		);
	}
}

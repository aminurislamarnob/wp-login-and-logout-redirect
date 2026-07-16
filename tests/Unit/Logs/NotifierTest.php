<?php
/**
 * Login-alert and digest email tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Logs;

use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use PluginizeLab\WpLoginLogoutRedirect\Logs\Notifier;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Notifier
 */
class NotifierTest extends TestCase {

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

		reset_phpmailer_instance();
		wp_clear_scheduled_hook( Notifier::ALERT_HOOK );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/*
	 * The enable gate.
	 */

	public function test_nothing_is_hooked_while_logging_is_off() {
		$notifier = new Notifier( $this->repository );

		$this->assertFalse( has_action( 'wplalr_log_recorded', array( $notifier, 'maybe_queue_login_alert' ) ) );
		$this->assertFalse( has_action( Notifier::DIGEST_HOOK, array( $notifier, 'send_digest' ) ) );
	}

	public function test_hooks_are_registered_once_logging_is_on() {
		$notifier = $this->enabled_notifier();

		$this->assertNotFalse( has_action( 'wplalr_log_recorded', array( $notifier, 'maybe_queue_login_alert' ) ) );
		$this->assertNotFalse( has_action( Notifier::ALERT_HOOK, array( $notifier, 'send_login_alert' ) ) );
		$this->assertNotFalse( has_action( Notifier::DIGEST_HOOK, array( $notifier, 'send_digest' ) ) );
	}

	/*
	 * Queueing per-event alerts.
	 */

	public function test_an_alert_is_queued_for_a_watched_role() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$notifier = $this->enabled_notifier();
		$admin    = $this->make_user( 'boss', 'administrator' );

		$row_id = $this->repository->insert(
			array(
				'event'    => 'login',
				'user_id'  => $admin->ID,
				'username' => 'boss',
			)
		);

		$notifier->maybe_queue_login_alert(
			$row_id,
			array(
				'event'   => 'login',
				'user_id' => $admin->ID,
			)
		);

		$this->assertNotFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( $row_id ) ) );
	}

	public function test_no_alert_for_an_unwatched_role() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$notifier   = $this->enabled_notifier();
		$subscriber = $this->make_user( 'zoe', 'subscriber' );

		$notifier->maybe_queue_login_alert(
			1,
			array(
				'event'   => 'login',
				'user_id' => $subscriber->ID,
			)
		);

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 1 ) ) );
	}

	public function test_no_alert_when_no_roles_are_watched() {
		$notifier = $this->enabled_notifier();
		$admin    = $this->make_user( 'boss', 'administrator' );

		$notifier->maybe_queue_login_alert(
			1,
			array(
				'event'   => 'login',
				'user_id' => $admin->ID,
			)
		);

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 1 ) ) );
	}

	public function test_no_alert_for_a_non_login_event() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$notifier = $this->enabled_notifier();
		$admin    = $this->make_user( 'boss', 'administrator' );

		$notifier->maybe_queue_login_alert(
			1,
			array(
				'event'   => 'failed',
				'user_id' => $admin->ID,
			)
		);

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 1 ) ) );
	}

	public function test_no_alert_without_a_user_id() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$notifier = $this->enabled_notifier();

		$notifier->maybe_queue_login_alert(
			1,
			array(
				'event'   => 'login',
				'user_id' => 0,
			)
		);

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 1 ) ) );
	}

	public function test_no_alert_for_a_deleted_user() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$notifier = $this->enabled_notifier();

		$notifier->maybe_queue_login_alert(
			1,
			array(
				'event'   => 'login',
				'user_id' => 999999,
			)
		);

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 1 ) ) );
	}

	public function test_recording_a_watched_login_queues_an_alert_through_the_action() {
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$this->enabled_notifier();
		$admin = $this->make_user( 'boss', 'administrator' );

		do_action(
			'wplalr_log_recorded',
			55,
			array(
				'event'   => 'login',
				'user_id' => $admin->ID,
			)
		);

		$this->assertNotFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( 55 ) ) );
	}

	/*
	 * Sending the alert.
	 */

	public function test_the_alert_email_carries_the_login_details() {
		$notifier = $this->enabled_notifier();
		$admin    = $this->make_user( 'boss', 'administrator' );

		update_option( 'wplalr_logs_notification_email', 'ops@example.test' );

		$row_id = $this->repository->insert(
			array(
				'event'    => 'login',
				'user_id'  => $admin->ID,
				'username' => 'boss',
				'ip'       => '203.0.113.9',
				'browser'  => 'Chrome',
			)
		);

		$notifier->send_login_alert( $row_id );

		$mail = $this->last_mail();

		$this->assertNotFalse( $mail );
		$this->assertSame( 'ops@example.test', $mail->to[0][0] );
		$this->assertStringContainsString( 'Login alert', $mail->subject );
		$this->assertStringContainsString( '203.0.113.9', $mail->body );
		$this->assertStringContainsString( 'Chrome', $mail->body );
	}

	public function test_the_alert_falls_back_to_the_admin_email() {
		$notifier = $this->enabled_notifier();

		$row_id = $this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		$notifier->send_login_alert( $row_id );

		$mail = $this->last_mail();

		$this->assertSame( get_option( 'admin_email' ), $mail->to[0][0] );
	}

	public function test_no_alert_is_sent_for_a_row_that_vanished() {
		$notifier = $this->enabled_notifier();

		$notifier->send_login_alert( 999999 );

		$this->assertFalse( $this->last_mail() );
	}

	public function test_the_alert_email_is_filterable() {
		$notifier = $this->enabled_notifier();

		$row_id = $this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		add_filter(
			'wplalr_log_notification_email',
			function ( $email ) {
				$email['to']      = 'security@example.test';
				$email['subject'] = 'Overridden';
				return $email;
			}
		);

		$notifier->send_login_alert( $row_id );

		$mail = $this->last_mail();

		$this->assertSame( 'security@example.test', $mail->to[0][0] );
		$this->assertSame( 'Overridden', $mail->subject );
	}

	public function test_filtering_the_recipient_to_empty_cancels_the_send() {
		$notifier = $this->enabled_notifier();

		$row_id = $this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		add_filter(
			'wplalr_log_notification_email',
			function ( $email ) {
				$email['to'] = '';
				return $email;
			}
		);

		$notifier->send_login_alert( $row_id );

		$this->assertFalse( $this->last_mail() );
	}

	/*
	 * Digest.
	 */

	public function test_no_digest_is_sent_when_the_cadence_is_off() {
		$notifier = $this->enabled_notifier();

		$notifier->send_digest();

		$this->assertFalse( $this->last_mail() );
	}

	public function test_the_digest_reports_the_window_counts() {
		$notifier = $this->enabled_notifier();

		update_option( 'wplalr_logs_digest', 'weekly' );
		update_option( 'wplalr_logs_notification_email', 'ops@example.test' );

		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'failed' ) );

		$notifier->send_digest();

		$mail = $this->last_mail();

		$this->assertNotFalse( $mail );
		$this->assertSame( 'ops@example.test', $mail->to[0][0] );
		$this->assertStringContainsString( 'Weekly login activity digest', $mail->subject );
		$this->assertStringContainsString( 'last 7 days', $mail->body );
		$this->assertStringContainsString( 'Successful logins: 2', $mail->body );
		$this->assertStringContainsString( 'Failed logins: 1', $mail->body );
		$this->assertStringContainsString( 'Total events: 3', $mail->body );
	}

	public function test_the_daily_digest_counts_only_the_last_day() {
		$notifier = $this->enabled_notifier();

		update_option( 'wplalr_logs_digest', 'daily' );

		$this->repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS ) ),
			)
		);
		$this->repository->insert( array( 'event' => 'login' ) );

		$notifier->send_digest();

		$mail = $this->last_mail();

		$this->assertStringContainsString( 'last 24 hours', $mail->body );
		$this->assertStringContainsString( 'Successful logins: 1', $mail->body );
	}

	public function test_the_digest_email_is_filterable() {
		$notifier = $this->enabled_notifier();

		update_option( 'wplalr_logs_digest', 'daily' );

		$captured = null;
		add_filter(
			'wplalr_log_digest_email',
			function ( $email, $context ) use ( &$captured ) {
				$captured    = $context;
				$email['to'] = 'reports@example.test';
				return $email;
			},
			10,
			2
		);

		$notifier->send_digest();

		$mail = $this->last_mail();

		$this->assertSame( 'reports@example.test', $mail->to[0][0] );
		$this->assertSame( 'daily', $captured['cadence'] );
		$this->assertArrayHasKey( 'total', $captured['stats'] );
	}

	public function test_emails_are_sent_as_html() {
		$notifier = $this->enabled_notifier();

		update_option( 'wplalr_logs_digest', 'daily' );

		$notifier->send_digest();

		$mail = $this->last_mail();

		$this->assertStringContainsString( 'text/html', implode( "\n", (array) $mail->header ) );
	}

	/**
	 * The most recent message handed to the mock mailer.
	 *
	 * @return object|false The sent message, or false when nothing was sent.
	 */
	protected function last_mail() {
		$mailer = tests_retrieve_phpmailer_instance();

		return $mailer ? $mailer->get_sent() : false;
	}

	/**
	 * Turn logging on and return a freshly hooked notifier.
	 *
	 * @return Notifier
	 */
	protected function enabled_notifier() {
		update_option( 'wplalr_enable_logs', 'yes' );

		return new Notifier( $this->repository );
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
}

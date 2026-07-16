<?php
/**
 * Cron-driven log retention and notification flows.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

use PluginizeLab\WpLoginLogoutRedirect\Logs\Installer;
use PluginizeLab\WpLoginLogoutRedirect\Logs\Notifier;

/**
 * The scheduled half of the audit log: rows ageing out, alerts firing, digests
 * going out. Each flow is driven by firing the cron hook the scheduler would.
 *
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Installer
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Notifier
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository
 */
class LogMaintenanceFlowTest extends IntegrationTestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		wp_clear_scheduled_hook( Installer::CLEANUP_HOOK );
		wp_clear_scheduled_hook( Installer::DIGEST_HOOK );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( Installer::CLEANUP_HOOK );
		wp_clear_scheduled_hook( Installer::DIGEST_HOOK );

		parent::tear_down();
	}

	/*
	 * Retention: rows ageing out on the daily cron.
	 */

	public function test_the_cleanup_cron_drops_rows_past_the_retention_window() {
		$this->enable_logs();

		update_option( 'wplalr_logs_retention_days', 30 );

		$this->log_row_aged( 45 );
		$this->log_row_aged( 10 );

		do_action( Installer::CLEANUP_HOOK );

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows, 'Only the row inside the window should survive.' );
		$this->assertSame( 'recent', $rows[0]['username'] );
	}

	public function test_the_cleanup_cron_keeps_a_row_on_the_edge_of_the_window() {
		$this->enable_logs();

		update_option( 'wplalr_logs_retention_days', 30 );

		$this->log_row_aged( 29 );

		do_action( Installer::CLEANUP_HOOK );

		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_a_retention_of_zero_keeps_everything_forever() {
		$this->enable_logs();

		update_option( 'wplalr_logs_retention_days', 0 );

		$this->log_row_aged( 500 );

		do_action( Installer::CLEANUP_HOOK );

		$this->assertCount( 1, $this->all_log_rows(), 'Zero means "keep forever", not "delete everything".' );
	}

	public function test_shortening_the_retention_window_prunes_on_the_next_run() {
		$this->enable_logs();

		update_option( 'wplalr_logs_retention_days', 90 );
		$this->log_row_aged( 45 );

		do_action( Installer::CLEANUP_HOOK );
		$this->assertCount( 1, $this->all_log_rows() );

		update_option( 'wplalr_logs_retention_days', 30 );
		do_action( Installer::CLEANUP_HOOK );

		$this->assertCount( 0, $this->all_log_rows() );
	}

	/*
	 * Scheduling.
	 */

	public function test_activating_the_plugin_schedules_the_cleanup() {
		( new Installer() )->install();

		$this->assertNotFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
	}

	public function test_installing_twice_does_not_double_schedule_the_cleanup() {
		$installer = new Installer();

		$installer->install();
		$first = wp_next_scheduled( Installer::CLEANUP_HOOK );

		$installer->install();

		$this->assertSame( $first, wp_next_scheduled( Installer::CLEANUP_HOOK ) );
	}

	public function test_choosing_a_digest_cadence_schedules_it() {
		update_option( 'wplalr_logs_digest', 'weekly' );

		$this->assertNotFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
		$this->assertSame( 'weekly', wp_get_schedule( Installer::DIGEST_HOOK ) );
	}

	public function test_changing_the_cadence_reschedules_rather_than_stacking() {
		update_option( 'wplalr_logs_digest', 'daily' );
		update_option( 'wplalr_logs_digest', 'monthly' );

		$this->assertSame( 'monthly', wp_get_schedule( Installer::DIGEST_HOOK ) );
	}

	public function test_the_monthly_cadence_is_available_even_though_core_has_no_such_schedule() {
		update_option( 'wplalr_logs_digest', 'monthly' );

		$this->assertNotFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_turning_the_digest_off_unschedules_it() {
		update_option( 'wplalr_logs_digest', 'weekly' );
		update_option( 'wplalr_logs_digest', '' );

		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_deactivating_the_plugin_clears_both_scheduled_events() {
		( new Installer() )->install();
		update_option( 'wplalr_logs_digest', 'weekly' );

		Installer::unschedule_all();

		$this->assertFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	/*
	 * Per-event login alerts.
	 */

	public function test_a_login_by_a_watched_role_queues_and_sends_an_alert() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );
		update_option( 'wplalr_logs_notification_email', 'security@example.com' );

		$admin = $this->make_user( 'administrator', array( 'user_login' => 'boss' ) );

		$this->sign_in( $admin );

		$scheduled = wp_next_scheduled( Notifier::ALERT_HOOK, array( (int) $this->only_log_row()['id'] ) );
		$this->assertNotFalse( $scheduled, 'The alert should be queued rather than sent inline.' );

		do_action( Notifier::ALERT_HOOK, (int) $this->only_log_row()['id'] );

		$mail = $this->last_mail();

		$this->assertNotNull( $mail, 'The queued alert should have been emailed.' );
		$this->assertSame( 'security@example.com', $mail->to[0][0] );
		$this->assertStringContainsString( 'boss', $mail->subject );
	}

	public function test_a_login_by_an_unwatched_role_queues_nothing() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$this->sign_in( $this->make_user( 'subscriber' ) );

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( (int) $this->only_log_row()['id'] ) ) );
		$this->assertNull( $this->last_mail() );
	}

	public function test_no_alert_is_queued_when_no_roles_are_watched() {
		$this->enable_logs();

		$this->sign_in( $this->make_user( 'administrator' ) );

		$this->assertNull( $this->last_mail() );
	}

	public function test_a_failed_login_does_not_raise_a_login_alert() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$admin = $this->make_user( 'administrator' );

		$this->fail_sign_in( $admin->user_login );

		$this->assertFalse( wp_next_scheduled( Notifier::ALERT_HOOK, array( (int) $this->only_log_row()['id'] ) ) );
	}

	public function test_an_alert_falls_back_to_the_site_admin_address() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$this->sign_in( $this->make_user( 'administrator' ) );

		do_action( Notifier::ALERT_HOOK, (int) $this->only_log_row()['id'] );

		$this->assertSame( get_option( 'admin_email' ), $this->last_mail()->to[0][0] );
	}

	public function test_an_alert_for_a_row_that_has_since_been_deleted_sends_nothing() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		$this->sign_in( $this->make_user( 'administrator' ) );

		$row_id = (int) $this->only_log_row()['id'];
		$this->truncate_logs();

		do_action( Notifier::ALERT_HOOK, $row_id );

		$this->assertNull( $this->last_mail() );
	}

	public function test_a_site_can_redirect_the_alert_somewhere_else() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		add_filter(
			'wplalr_log_notification_email',
			function ( $email ) {
				$email['to'] = 'soc@example.com';
				return $email;
			}
		);

		$this->sign_in( $this->make_user( 'administrator' ) );

		do_action( Notifier::ALERT_HOOK, (int) $this->only_log_row()['id'] );

		$this->assertSame( 'soc@example.com', $this->last_mail()->to[0][0] );
	}

	public function test_a_site_can_suppress_the_alert_entirely() {
		$this->enable_logs();

		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );

		add_filter(
			'wplalr_log_notification_email',
			function ( $email ) {
				$email['to'] = '';
				return $email;
			}
		);

		$this->sign_in( $this->make_user( 'administrator' ) );

		do_action( Notifier::ALERT_HOOK, (int) $this->only_log_row()['id'] );

		$this->assertNull( $this->last_mail() );
	}

	/*
	 * The digest.
	 */

	public function test_the_digest_cron_sends_a_roll_up_of_the_window() {
		$this->enable_logs();

		update_option( 'wplalr_logs_digest', 'weekly' );
		update_option( 'wplalr_logs_notification_email', 'security@example.com' );

		$this->log_row_aged( 1, 'login' );
		$this->log_row_aged( 2, 'login' );
		$this->log_row_aged( 3, 'failed' );

		do_action( Notifier::DIGEST_HOOK );

		$mail = $this->last_mail();

		$this->assertNotNull( $mail );
		$this->assertSame( 'security@example.com', $mail->to[0][0] );
		$this->assertStringContainsString( 'Weekly', $mail->subject );
		$this->assertStringContainsString( 'Successful logins', $mail->body );
	}

	public function test_the_digest_counts_only_rows_inside_its_window() {
		$this->enable_logs();

		update_option( 'wplalr_logs_digest', 'weekly' );

		$this->log_row_aged( 2, 'login' );
		$this->log_row_aged( 40, 'login' );

		do_action( Notifier::DIGEST_HOOK );

		// One login in the last 7 days, not the two on the table.
		$this->assertRegExp( '/Successful logins[^0-9]*1/', wp_strip_all_tags( $this->last_mail()->body ) );
	}

	public function test_the_digest_sends_nothing_when_no_cadence_is_set() {
		$this->enable_logs();

		$this->log_row_aged( 1, 'login' );

		do_action( Notifier::DIGEST_HOOK );

		$this->assertNull( $this->last_mail() );
	}

	public function test_a_site_can_redirect_the_digest_somewhere_else() {
		$this->enable_logs();

		update_option( 'wplalr_logs_digest', 'daily' );

		add_filter(
			'wplalr_log_digest_email',
			function ( $email ) {
				$email['to'] = 'reports@example.com';
				return $email;
			}
		);

		do_action( Notifier::DIGEST_HOOK );

		$this->assertSame( 'reports@example.com', $this->last_mail()->to[0][0] );
	}

	public function test_nothing_is_emailed_while_logging_is_switched_off() {
		// No enable_logs(): the Notifier the plugin booted hooked nothing.
		update_option( 'wplalr_logs_notify_roles', array( 'administrator' ) );
		update_option( 'wplalr_logs_digest', 'daily' );

		$this->sign_in( $this->make_user( 'administrator' ) );
		do_action( Notifier::DIGEST_HOOK );

		$this->assertNull( $this->last_mail() );
	}

	/**
	 * Insert a log row backdated by a number of days.
	 *
	 * @param int    $days_ago How far back to date the row.
	 * @param string $event    Event name.
	 * @return int The row id.
	 */
	protected function log_row_aged( $days_ago, $event = 'login' ) {
		return ( new \PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository() )->insert(
			array(
				'event'      => $event,
				'username'   => $days_ago > 30 ? 'ancient' : 'recent',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( $days_ago * DAY_IN_SECONDS ) ),
			)
		);
	}
}

<?php
/**
 * Schema/cron installer tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Logs;

use PluginizeLab\WpLoginLogoutRedirect\Logs\Installer;
use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Installer
 */
class InstallerTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var Installer
	 */
	protected $installer;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->installer = new Installer();

		Installer::unschedule_all();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		Installer::unschedule_all();

		parent::tear_down();
	}

	public function test_table_name_is_prefixed() {
		global $wpdb;

		$this->assertSame( $wpdb->prefix . 'wplalr_auth_logs', Installer::table_name() );
	}

	public function test_the_table_exists_after_install() {
		global $wpdb;

		$table = Installer::table_name();

		// The bootstrap installs it; this asserts the schema is really there.
		$this->assertSame( $table, $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) );
	}

	public function test_the_table_carries_every_column_the_repository_writes() {
		global $wpdb;

		$table   = Installer::table_name();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

		$expected = array(
			'id',
			'user_id',
			'username',
			'event',
			'status',
			'redirect_url',
			'rule_id',
			'ip',
			'agent',
			'browser',
			'device_os',
			'error_code',
			'description',
			'created_at',
		);

		foreach ( $expected as $column ) {
			$this->assertContains( $column, $columns );
		}
	}

	public function test_maybe_install_is_a_noop_when_the_schema_version_matches() {
		update_option( Installer::DB_VERSION_OPTION, Installer::DB_VERSION );

		$this->installer->maybe_install();

		// A no-op must not (re)schedule anything, which install() would.
		$this->assertFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
	}

	public function test_maybe_install_reinstalls_when_the_stored_version_is_stale() {
		update_option( Installer::DB_VERSION_OPTION, '0.0.1' );

		$this->installer->maybe_install();

		$this->assertSame( Installer::DB_VERSION, get_option( Installer::DB_VERSION_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
	}

	public function test_the_monthly_schedule_is_registered() {
		$schedules = $this->installer->register_schedules( array() );

		$this->assertArrayHasKey( 'monthly', $schedules );
		$this->assertSame( 30 * DAY_IN_SECONDS, $schedules['monthly']['interval'] );
	}

	public function test_registering_schedules_leaves_existing_ones_alone() {
		$existing  = array(
			'monthly' => array(
				'interval' => 123,
				'display'  => 'Someone else',
			),
		);
		$schedules = $this->installer->register_schedules( $existing );

		$this->assertSame( 123, $schedules['monthly']['interval'] );
	}

	public function test_monthly_is_available_to_wp_cron() {
		$this->assertArrayHasKey( 'monthly', wp_get_schedules() );
	}

	public function test_schedule_cleanup_registers_a_daily_event() {
		$this->installer->schedule_cleanup();

		$this->assertNotFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( Installer::CLEANUP_HOOK ) );
	}

	public function test_schedule_cleanup_does_not_double_schedule() {
		$this->installer->schedule_cleanup();
		$first = wp_next_scheduled( Installer::CLEANUP_HOOK );

		$this->installer->schedule_cleanup();

		$this->assertSame( $first, wp_next_scheduled( Installer::CLEANUP_HOOK ) );
	}

	public function test_unschedule_all_clears_both_events() {
		$this->installer->schedule_cleanup();
		wp_schedule_event( time() + 60, 'daily', Installer::DIGEST_HOOK );

		Installer::unschedule_all();

		$this->assertFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_unschedule_cleanup_clears_only_the_cleanup_event() {
		$this->installer->schedule_cleanup();
		wp_schedule_event( time() + 60, 'daily', Installer::DIGEST_HOOK );

		Installer::unschedule_cleanup();

		$this->assertFalse( wp_next_scheduled( Installer::CLEANUP_HOOK ) );
		$this->assertNotFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	/*
	 * Digest scheduling.
	 */

	public function test_setting_a_digest_cadence_schedules_the_event() {
		$this->installer->reschedule_digest( '', 'weekly' );

		$this->assertNotFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
		$this->assertSame( 'weekly', wp_get_schedule( Installer::DIGEST_HOOK ) );
	}

	public function test_changing_the_cadence_replaces_the_event() {
		$this->installer->reschedule_digest( '', 'daily' );
		$this->installer->reschedule_digest( 'daily', 'monthly' );

		$this->assertSame( 'monthly', wp_get_schedule( Installer::DIGEST_HOOK ) );
	}

	public function test_clearing_the_cadence_unschedules_the_event() {
		$this->installer->reschedule_digest( '', 'daily' );
		$this->installer->reschedule_digest( 'daily', '' );

		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_an_invalid_cadence_leaves_the_digest_unscheduled() {
		$this->installer->reschedule_digest( '', 'hourly' );

		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_a_non_string_cadence_is_ignored() {
		$this->installer->reschedule_digest( '', array( 'daily' ) );

		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	public function test_adding_the_option_the_first_time_schedules_the_digest() {
		$this->installer->reschedule_digest_on_add( 'wplalr_logs_digest', 'daily' );

		$this->assertSame( 'daily', wp_get_schedule( Installer::DIGEST_HOOK ) );
	}

	public function test_writing_the_option_reschedules_via_the_hook() {
		// Installer's constructor wires add_option_/update_option_ listeners.
		update_option( 'wplalr_logs_digest', 'weekly' );

		$this->assertSame( 'weekly', wp_get_schedule( Installer::DIGEST_HOOK ) );

		update_option( 'wplalr_logs_digest', '' );

		$this->assertFalse( wp_next_scheduled( Installer::DIGEST_HOOK ) );
	}

	/*
	 * Cleanup cron.
	 */

	public function test_cleanup_purges_rows_past_the_retention_window() {
		$repository = new LogRepository();

		$repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'ancient',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 40 * DAY_IN_SECONDS ) ),
			)
		);
		$repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'recent',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		update_option( 'wplalr_logs_retention_days', 30 );

		$this->installer->run_cleanup();

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'recent', $rows[0]['username'] );
	}

	public function test_cleanup_defaults_to_a_30_day_window() {
		$repository = new LogRepository();

		$repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 40 * DAY_IN_SECONDS ) ),
			)
		);

		// No option set at all.
		$this->installer->run_cleanup();

		$this->assertCount( 0, $this->all_log_rows() );
	}

	public function test_a_retention_of_zero_keeps_everything() {
		$repository = new LogRepository();

		$repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) ),
			)
		);

		update_option( 'wplalr_logs_retention_days', 0 );

		$this->installer->run_cleanup();

		$this->assertCount( 1, $this->all_log_rows() );
	}
}

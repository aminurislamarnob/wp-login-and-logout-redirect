<?php
/**
 * Shared base test case.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests;

use PluginizeLab\WpLoginLogoutRedirect\Logs\Installer;
use WP_UnitTestCase;

/**
 * Base class with the option/table reset every suite needs.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Plugin options that must not leak between tests.
	 *
	 * @var string[]
	 */
	protected $plugin_options = array(
		'wplalr_login_redirect',
		'wplalr_logout_redirect',
		'wplalr_redirect_rules',
		'wplalr_enable_logs',
		'wplalr_logs_retention_days',
		'wplalr_logs_notification_email',
		'wplalr_logs_notify_roles',
		'wplalr_logs_digest',
	);

	/**
	 * Reset plugin state before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		foreach ( $this->plugin_options as $option ) {
			delete_option( $option );
		}

		$this->truncate_logs();
	}

	/**
	 * Reset plugin state after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->plugin_options as $option ) {
			delete_option( $option );
		}

		parent::tear_down();
	}

	/**
	 * Empty the audit-log table.
	 *
	 * The log table is not one of WP's known tables, and TRUNCATE/DDL commits the
	 * surrounding transaction anyway, so rows are cleared explicitly rather than
	 * relying on WP_UnitTestCase's rollback.
	 *
	 * @return void
	 */
	protected function truncate_logs() {
		global $wpdb;

		$table = Installer::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Read every row of the log table, oldest first.
	 *
	 * @return array
	 */
	protected function all_log_rows() {
		global $wpdb;

		$table = Installer::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
	}

	/**
	 * Build a rule array with sane defaults.
	 *
	 * @param array $overrides Fields to override.
	 * @return array
	 */
	protected function make_rule( array $overrides = array() ) {
		return array_merge(
			array(
				'id'         => wp_generate_uuid4(),
				'enabled'    => true,
				'label'      => 'Test rule',
				'conditions' => array(),
				'login_url'  => '',
				'logout_url' => '',
			),
			$overrides
		);
	}

	/**
	 * Persist a set of rules to the rules option.
	 *
	 * @param array $rules Rule arrays.
	 * @return void
	 */
	protected function set_rules( array $rules ) {
		update_option( 'wplalr_redirect_rules', $rules );
	}
}

<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the audit-log table and owns the cleanup cron.
 */
class Installer {

	/**
	 * Schema version. Bump when the table definition changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option storing the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'wplalr_db_version';

	/**
	 * Daily cleanup cron hook.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'wplalr_logs_cleanup';

	/**
	 * The constructor.
	 */
	public function __construct() {
		// Migrate on upgrades that don't re-run the activation hook.
		add_action( 'admin_init', array( $this, 'maybe_install' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'run_cleanup' ) );
	}

	/**
	 * Fully qualified log table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wplalr_auth_logs';
	}

	/**
	 * Install the table when the stored schema version is missing or stale.
	 *
	 * @return void
	 */
	public function maybe_install() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		$this->install();
	}

	/**
	 * Create/upgrade the table via dbDelta and (re)schedule the cleanup cron.
	 *
	 * @return void
	 */
	public function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta is whitespace-sensitive: two spaces after PRIMARY KEY, KEY (not INDEX).
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			username varchar(192) NOT NULL DEFAULT '',
			event varchar(20) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'success',
			redirect_url varchar(255) DEFAULT NULL,
			rule_id varchar(64) DEFAULT NULL,
			ip varchar(50) DEFAULT NULL,
			agent varchar(255) DEFAULT NULL,
			browser varchar(50) DEFAULT NULL,
			device_os varchar(50) DEFAULT NULL,
			error_code varchar(50) DEFAULT '',
			description tinytext DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY user_id (user_id),
			KEY event (event),
			KEY ip (ip)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		$this->schedule_cleanup();
	}

	/**
	 * Schedule the daily cleanup event if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Clear the cleanup event. Called on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
		}
	}

	/**
	 * Cron handler: prune rows older than the configured retention window.
	 *
	 * @return void
	 */
	public function run_cleanup() {
		$days = (int) get_option( 'wplalr_logs_retention_days', 30 );

		if ( $days > 0 ) {
			( new LogRepository() )->purge_older_than( $days );
		}
	}
}

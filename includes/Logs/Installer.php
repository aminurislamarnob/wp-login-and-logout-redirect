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
	 * Recurring digest cron hook.
	 *
	 * @var string
	 */
	const DIGEST_HOOK = 'wplalr_logs_digest_send';

	/**
	 * The constructor.
	 */
	public function __construct() {
		// Migrate on upgrades that don't re-run the activation hook.
		add_action( 'admin_init', array( $this, 'maybe_install' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'run_cleanup' ) );

		// `monthly` is not a core schedule; register it for the digest.
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );

		// (Re)schedule the digest whenever its cadence option changes.
		add_action( 'add_option_wplalr_logs_digest', array( $this, 'reschedule_digest_on_add' ), 10, 2 );
		add_action( 'update_option_wplalr_logs_digest', array( $this, 'reschedule_digest' ), 10, 2 );
	}

	/**
	 * Register the non-core `monthly` cron schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_schedules( $schedules ) {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'wp-login-logout-redirect' ),
			);
		}

		return $schedules;
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
	 * Clear all scheduled events. Called on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_all() {
		self::clear_event( self::CLEANUP_HOOK );
		self::clear_event( self::DIGEST_HOOK );
	}

	/**
	 * Clear the cleanup event. Retained for back-compat.
	 *
	 * @return void
	 */
	public static function unschedule_cleanup() {
		self::clear_event( self::CLEANUP_HOOK );
	}

	/**
	 * Unschedule every occurrence of a hook.
	 *
	 * @param string $hook Cron hook name.
	 * @return void
	 */
	protected static function clear_event( $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Schedule the digest the first time the option is added.
	 *
	 * @param string $option Option name (unused).
	 * @param mixed  $value  The new cadence value.
	 * @return void
	 */
	public function reschedule_digest_on_add( $option, $value ) {
		$this->reschedule_digest( '', $value );
	}

	/**
	 * Clear and (re)schedule the digest event for the given cadence.
	 *
	 * @param mixed $old_value Previous cadence (unused).
	 * @param mixed $new_value New cadence: '' | daily | weekly | monthly.
	 * @return void
	 */
	public function reschedule_digest( $old_value, $new_value ) {
		self::clear_event( self::DIGEST_HOOK );

		$cadence = is_string( $new_value ) ? $new_value : '';

		if ( in_array( $cadence, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $cadence, self::DIGEST_HOOK );
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

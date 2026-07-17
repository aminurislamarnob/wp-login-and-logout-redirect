<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class Settings {
	/**
	 * Hook suffixes for our admin pages, keyed by view.
	 *
	 * Populated on `admin_menu`; read by Assets to enqueue the shared bundle on
	 * exactly our screens without hardcoding screen-id strings.
	 *
	 * @var array
	 */
	private static $page_hooks = array();

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'login_logout_redirect_menu' ) );
		add_filter( 'plugin_action_links_' . WP_LOGIN_LOGOUT_REDIRECT_BASENAME, array( $this, 'plugin_action_link' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Register plugin admin menu and submenu pages.
	 *
	 * All three pages share one React bundle (see Assets); each renders its own
	 * mount node and the app boots the matching view.
	 */
	public function login_logout_redirect_menu() {
		$settings = add_menu_page(
			__( 'WP Login and Logout Redirect Options', 'wp-login-logout-redirect' ),
			__( 'Redirect Options', 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_login_logout_redirect',
			array( $this, 'login_logout_redirect_settings_form' ),
			'dashicons-randomize'
		);

		// Duplicate the parent as the first submenu so the cleaner label shows.
		add_submenu_page(
			'wplalr_login_logout_redirect',
			__( 'Redirect Options', 'wp-login-logout-redirect' ),
			__( 'Redirect Options', 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_login_logout_redirect',
			array( $this, 'login_logout_redirect_settings_form' )
		);

		$audit_logs = add_submenu_page(
			'wplalr_login_logout_redirect',
			__( 'Audit Logs', 'wp-login-logout-redirect' ),
			__( 'Audit Logs', 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_audit_logs',
			array( $this, 'render_audit_logs_page' )
		);

		$sessions = add_submenu_page(
			'wplalr_login_logout_redirect',
			__( 'Logged-in Users', 'wp-login-logout-redirect' ),
			__( 'Logged-in Users', 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_sessions',
			array( $this, 'render_sessions_page' )
		);

		$whats_new = add_submenu_page(
			'wplalr_login_logout_redirect',
			__( "What's New", 'wp-login-logout-redirect' ),
			__( "What's New", 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_whats_new',
			array( $this, 'render_whats_new_page' )
		);

		self::$page_hooks = array(
			'settings'   => $settings,
			'audit_logs' => $audit_logs,
			'sessions'   => $sessions,
			'whats_new'  => $whats_new,
		);
	}

	/**
	 * Get the captured hook suffixes for our admin pages.
	 *
	 * @return array Keyed by view: settings|audit_logs|sessions.
	 */
	public static function get_page_hooks() {
		return self::$page_hooks;
	}

	/**
	 * Add a shared body class on our admin screens so CSS can target all of
	 * them (the per-screen body classes differ between the top-level and
	 * submenu pages).
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();

		if ( $screen && in_array( $screen->id, self::$page_hooks, true ) ) {
			$classes .= ' wplalr-admin-page';
		}

		return $classes;
	}

	/**
	 * Render the Redirects + Rules app mount point.
	 */
	public function login_logout_redirect_settings_form() {
		echo '<div id="wplalr-settings"></div>';
	}

	/**
	 * Render the Audit Logs app mount point.
	 */
	public function render_audit_logs_page() {
		echo '<div id="wplalr-audit-logs"></div>';
	}

	/**
	 * Render the Logged-in Users app mount point.
	 */
	public function render_sessions_page() {
		echo '<div id="wplalr-sessions"></div>';
	}

	/**
	 * Render the What's New app mount point.
	 */
	public function render_whats_new_page() {
		echo '<div id="wplalr-whats-new"></div>';
	}

	/**
	 * Add settings page link with plugin.
	 */
	public function plugin_action_link( $links ) {
		$wplalr_login_logout_plugin_action_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wplalr_login_logout_redirect' ) ) . '"> ' . __( 'Settings', 'wp-login-logout-redirect' ) . '</a>',
		);
		return array_merge( $links, $wplalr_login_logout_plugin_action_links );
	}
}

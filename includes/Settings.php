<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class Settings {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'login_logout_redirect_menu' ) );
		add_filter( 'plugin_action_links_' . WP_LOGIN_LOGOUT_REDIRECT_BASENAME, array( $this, 'plugin_action_link' ) );
	}

	/**
	 * Register plugin admin menu
	 */
	public function login_logout_redirect_menu() {
		add_menu_page(
			__( 'WP Login and Logout Redirect Options', 'wp-login-logout-redirect' ),
			__( 'Redirect Options', 'wp-login-logout-redirect' ),
			'manage_options',
			'wplalr_login_logout_redirect',
			array( $this, 'login_logout_redirect_settings_form' ),
			'dashicons-randomize'
		);
	}

	/**
	 * Render the React settings app mount point.
	 */
	public function login_logout_redirect_settings_form() {
		echo '<div id="wplalr-settings"></div>';
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

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
			$this->get_menu_icon()
		);
	}

	/**
	 * The admin menu icon, as a base64-encoded SVG data URI.
	 *
	 * Uses the Heroicons (MIT) "Squares2x2" outline glyph to match the icon
	 * shown in the settings page header. WordPress renders menu SVGs on a dark
	 * background without recoloring, so the default admin icon gray is baked in.
	 *
	 * @return string
	 */
	protected function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#a7aaad"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
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

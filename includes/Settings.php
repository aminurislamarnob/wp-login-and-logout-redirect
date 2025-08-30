<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class Settings {
	/**
	 * The constructor.
	 */
	public function __construct() {
        add_action( 'admin_menu', array( $this, 'login_logout_redirect_menu' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_link' ) );
        add_action( 'admin_init', array( $this, 'register_login_logout_settings' ) );
	}

    /**
     * Register plugin admin menu
     */
    public function login_logout_redirect_menu() {
        add_menu_page(__('WP Login and Logout Redirect Options', 'wp-login-logout-redirect'), __('Redirect Options', 'wp-login-logout-redirect'), 'manage_options', 'wplalr_login_logout_redirect', array( $this, 'login_logout_redirect_settings_form' ), 'dashicons-randomize');
    }

    /**
     * Plugin options form
     */
    public function login_logout_redirect_settings_form() {
        settings_errors();
        require_once pluginizelab_wp_login_logout_redirect()->get_template( 'settings-form.php' );
    }

    /**
     * Add settings page link with plugin.
     */
    public function plugin_action_link( $links ){
        $wplalr_login_logout_plugin_action_links = array(
        '<a href="' . esc_url( admin_url( 'admin.php?page=wplalr_login_logout_redirect' ) ) . '"> '. __('Settings', 'wp-login-logout-redirect') . '</a>',
        );
        return array_merge( $links, $wplalr_login_logout_plugin_action_links );
    }

	/**
     * Plugin settings page
     */
    public function register_login_logout_settings() {
        
        // register a new section
        add_settings_section(
            'wplalr_login_logout_settings_section', 
            __('WP Login and Logout Redirect Options', 'wp-login-logout-redirect'), array( $this, 'login_logout_section_text' ), 
            'wplalr_login_logout_section'
        );

        // register a new field in the "wplalr_login_logout_settings_section" section
        add_settings_field(
            'wplalr_login_redirect', 
            __('Login Redirect URL','wp-login-logout-redirect'), array( $this, 'login_field_callback' ), 
            'wplalr_login_logout_section',  
            'wplalr_login_logout_settings_section'
        );

        // register a new setting for login redirect field
        register_setting('wplalr_login_logout_settings_section', 'wplalr_login_redirect');

        // register a new field in the "wplalr_login_logout_settings_section" section
        add_settings_field(
            'wplalr_logout_redirect', 
            __('Logout Redirect URL', 'wp-login-logout-redirect'), array( $this, 'logout_field_callback' ), 
            'wplalr_login_logout_section',  
            'wplalr_login_logout_settings_section'
        );

        // register a new setting for logout redirect field
        register_setting('wplalr_login_logout_settings_section', 'wplalr_logout_redirect');
    }

    /**
     * Login redirect url field
     */
    public function login_field_callback(){
        $wplalr_login_redirect_value = get_option('wplalr_login_redirect');

        //Using esc_url() here because of here expecting url input
        printf('<div><input name="wplalr_login_redirect" type="text" class="regular-text" value="%s" placeholder="%s"/></div>', esc_url( $wplalr_login_redirect_value ), esc_attr(get_home_url() . '/example-login-redirect-link/'));

        echo '<small>' . esc_html__('Enter the URL to which the user will be redirected after a successful login.', 'wp-login-logout-redirect') . '</small>';
    }

    /**
     * Logout redirect url field
     */
    public function logout_field_callback() {
        $wplalr_logout_redirect_value = get_option('wplalr_logout_redirect');
        printf('<div><input name="wplalr_logout_redirect" type="text" class="regular-text" value="%s"  placeholder="%s"/></div>', esc_url($wplalr_logout_redirect_value), esc_attr(get_home_url() . '/example-logout-redirect-link/'));  //Using esc_url() here because of here expecting url input

        echo '<small>' . esc_html__('Enter the URL to which the user will be redirected after a successful logout.', 'wp-login-logout-redirect') . '</small>';
    }

    /**
     * Plugin settings page section text
     */
    public function login_logout_section_text() {
        printf('%s %s %s', '<p>', esc_html__('You can change WordPress Default login or logout or both redirect URL', 'wp-login-logout-redirect'), '</p>');
    }
}

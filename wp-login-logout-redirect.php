<?php
/*
Plugin Name: WP Login and Logout Redirect
Plugin URI: https://wordpress.org/plugins/wp-login-and-logout-redirect/
Description: This plugin which enables you to redirect users to specific URL on login or logout or both.
Version: 1.0
Author: Aminur Islam
Author URI: https://github.com/aminurislamarnob
License: GPLv2 or later
Text Domain: wp-login-logout-redirect
Domain Path: /languages
*/


/**
 * Restrict this file to call directly
*/
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Currently plugin version.
*/
define('JFB_PLUGIN_VERSION', '1.0');

 
/**
 * Load plugin textdomain.
 */
function jfb_login_logout_load_textdomain() {
    load_plugin_textdomain( 'wp-login-logout-redirect', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'init', 'jfb_login_logout_load_textdomain' );


/**
 * Plugin settings page
 */
function jfb_login_logout_register() {
    
    // register a new section
    add_settings_section(
        'jfb_login_logout_settings_section', 
        __('WP Login and Logout Redirect Options', 'wp-login-logout-redirect'), 'jfb_login_logout_section_text', 
        'jfb_login_logout_section'
    );

    // register a new field in the "jfb_login_logout_settings_section" section
    add_settings_field(
        'jfb_login_redirect', 
        __('Login Redirect URL','wp-login-logout-redirect'), 'jfb_login_field_callback', 
        'jfb_login_logout_section',  
        'jfb_login_logout_settings_section'
    );

    // register a new setting for login redirect field
	register_setting('jfb_login_logout_settings_section', 'jfb_login_redirect');

    // register a new field in the "jfb_login_logout_settings_section" section
	add_settings_field(
        'jfb_logout_redirect', 
        __('Logout Redirect URL', 'wp-login-logout-redirect'), 'jfb_logout_field_callback', 
        'jfb_login_logout_section',  
        'jfb_login_logout_settings_section'
    );

    // register a new setting for logout redirect field
	register_setting('jfb_login_logout_settings_section', 'jfb_logout_redirect');

}
add_action('admin_init', 'jfb_login_logout_register');


//Login redirect field content
function jfb_login_field_callback(){
    $jfb_login_redirect_value = get_option('jfb_login_redirect');
	printf('<input name="jfb_login_redirect" type="text" class="regular-text" value="%s"/>', $jfb_login_redirect_value);
}

//Logout redirect field content
function jfb_logout_field_callback() {
    $jfb_logout_redirect_value = get_option('jfb_logout_redirect');
	printf('<input name="jfb_logout_redirect" type="text" class="regular-text" value="%s"/>', $jfb_logout_redirect_value);
}

//Plugin settings page section text
function jfb_login_logout_section_text() {
	printf('%s %s %s', '<p>', __('You can change WordPress Default login or logout or both redirect URL', 'wp-login-logout-redirect'), '</p>');
}


//Register plugin admin menu
function jfb_login_logout_redirect_menu() {
	add_menu_page(__('WP Login and Logout Redirect Options', 'wp-login-logout-redirect'), __('Redirect Options', 'wp-login-logout-redirect'), 'manage_options', 'jfb_login_logout_redirect', 'jfb_login_logout_redirect_output', 'dashicons-randomize');
}
add_action('admin_menu', 'jfb_login_logout_redirect_menu');


//Plugin options form
function jfb_login_logout_redirect_output(){
    settings_errors();
    ?>
	<form action="options.php" method="POST">
		<?php do_settings_sections('jfb_login_logout_section');?>
		<?php settings_fields('jfb_login_logout_settings_section');?>
		<?php submit_button();?>
	</form>
<?php }


/**
 * Add settings page link with plugin.
 */
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'jfb_login_logout_action_links' );
function jfb_login_logout_action_links( $links ){
    $jfb_login_logout_plugin_action_links = array(
    '<a href="' . admin_url( 'admin.php?page=jfb_login_logout_redirect' ) . '"> '. __('Settings', 'wp-login-logout-redirect') . '</a>',
    );
    return array_merge( $links, $jfb_login_logout_plugin_action_links );
}


/**
 * Login redirect to user specific URL.
 */
function jfb_wp_login_redirect( $redirect_to, $request, $user ) {
    $redirect_to =  get_option('jfb_login_redirect');

    if(empty($redirect_to)){
        $redirect_to = admin_url();
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'jfb_wp_login_redirect', 10, 3 );


/**
 * Logout redirect to user specific URL.
 */
function jfb_wp_logout_redirect(){
    $jfb_logout_redirect =  get_option('jfb_logout_redirect');

    if(empty($jfb_logout_redirect)){
        $jfb_logout_redirect = home_url();
    }

    wp_redirect( $jfb_logout_redirect );
    exit();
}
add_action('wp_logout', 'jfb_wp_logout_redirect');
<?php
/*
Plugin Name: WP Login and Logout Redirect
Plugin URI: https://wordpress.org/plugins/wp-login-and-logout-redirect/
Description: This plugin which enables you to redirect users to specific URL on login or logout or both.
Version: 1.2
Tested up to: 6.0
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
define('WPLALR_PLUGIN_VERSION', '1.0');


/**
	* Plugin Dir
	*/
define( 'WPLALR_PLUGIN', __FILE__ );
define( 'WPLALR_PLUGIN_DIR', untrailingslashit( dirname( WPLALR_PLUGIN ) ) );

/**
 * Load plugin textdomain.
 */
function wplalr_login_logout_load_textdomain() {
    load_plugin_textdomain( 'wp-login-logout-redirect', false, basename( dirname( __FILE__ ) ) . '/languages' );
}


/**
 * Plugin settings page
 */
function wplalr_login_logout_register() {

    // register a new section
	//if (is_admin()) {
		register_setting(
				'wplalr_login_logout_settings_section',
				'wplalr_login_logout_data',
				array(
						'default'      => '',
						'show_in_rest' => true,
						'type'         => 'string',
				)
		);
}

//Register plugin admin menu
function wplalr_login_logout_redirect_menu() {
	add_menu_page(
   __('WP Login and Logout Redirect Options', 'wp-login-logout-redirect'),
   __('Redirect Options', 'wp-login-logout-redirect'),
   'manage_options',
   'wplalr_login_logout_redirect',
   'wplalr_login_logout_redirect_output',
   'dashicons-randomize');
}


//Plugin options form
function wplalr_login_logout_redirect_output(){
    ?>
 <div id="wplalr-login-logout-plugin-settings"></div>
<?php }


/**
 * Add settings page link with plugin.
 */
function wplalr_login_logout_action_links( $links ){
    $wplalr_login_logout_plugin_action_links = array(
    '<a href="' . admin_url( 'admin.php?page=wplalr_login_logout_redirect' ) . '"> '. __('Settings', 'wp-login-logout-redirect') . '</a>',
    );
    return array_merge( $links, $wplalr_login_logout_plugin_action_links );
}


/**
 * Login redirect to user specific URL.
 */
function wplalr_wp_login_redirect( $redirect_to, $request, $user ) {
				if (isset( $user->roles ) && is_array( $user->roles )) {

								if(empty($redirect_to)){
									$redirect_to = admin_url();
								}

							$redirect_data = wplalr_get_redirect_data($user->roles[0]);
							if ($redirect_data[0]['login_url']) {
									$redirect_to = $redirect_data[0]['login_url'];
								}

				}
				return $redirect_to;

}



/**
 * Logout redirect to user specific URL.
 */
function wplalr_wp_logout_redirect($redirect_to, $requested_redirect_to, $user){
	$wplalr_logout_redirect = home_url();
	if ($user->ID && $user?->roles[0]) {
		$redirect_data = wplalr_get_redirect_data($user->roles[0]);
		if ($redirect_data[0]['logout_url']) {
			$wplalr_logout_redirect = $redirect_data[0]['logout_url'];
		}
	}
	wp_redirect( $wplalr_logout_redirect );
	exit();
}


function wplalr_get_roles_array() {
	$editable_roles = get_editable_roles();
	foreach ($editable_roles as $role => $details) {
		$sub['role'] = esc_attr($role);
		$sub['name'] = translate_user_role($details['name']);
		$roles[] = $sub;
	}
	return $roles;
}

function wplalr_get_redirect_data($role) {
	$redirect_data = json_decode(get_option('wplalr_login_logout_data'), true);

	$redirect_to = array_filter($redirect_data, function ($data) use ($role) {
		return in_array($role, $data['roles']) ?? true;
	});

	return array_values($redirect_to);
}
// Register and enqueue admin scripts
function wplalr_login_logout_redirect_admin_scripts() {
	$dir = __DIR__;

	$script_asset_path = "$dir/build/admin.asset.php";

	if ( ! file_exists( $script_asset_path ) ) {
		throw new Error(
				'You need to run `npm start` or `npm run build` to build the asset files first.'
		);
	}
	//$admin_js     = 'build/admin.js';
	$admin_js     = plugins_url( '/', __FILE__ ) . 'build/admin.js';
//	$script_asset = require( $script_asset_path );
//	wp_register_script(
//			'wplalr-login-logout-redirect-admin',
//			plugins_url( $admin_js, __FILE__ ),
//			$script_asset['dependencies'],
//			$script_asset['version']
//	);

	wp_enqueue_script(
			'wplalr-login-logout-redirect-admin',
			$admin_js,
			array('react', 'react-dom', 'wp-api', 'wp-components', 'wp-dom-ready', 'wp-element', 'wp-i18n'),
			1,
			true
	);

	$data = [
			'roles' => wplalr_get_roles_array(),
	];

	wp_add_inline_script(
			'wplalr-login-logout-redirect-admin',
			'var loginlogoutData = ' . wp_json_encode( $data ),
			'before'
	);

	wp_set_script_translations(
			'wplalr-login-logout-redirect-admin',
			'wp-login-logout-redirect'
	);

	$admin_css = 'build/admin.css';
	wp_enqueue_style(
			'wplalr-login-logout-redirect-admin',
			plugins_url( $admin_css, __FILE__ ),
			['wp-components'],
			filemtime( "$dir/$admin_css" )
	);
}

/**
	* Include user functions
	*/
require_once WPLALR_PLUGIN_DIR . '/includes/login-user-time/login-user-time.php';
// @TODO load roles correctly in selct fields.
add_action( 'init', 'wplalr_login_logout_load_textdomain' );
add_action('init', 'wplalr_login_logout_register');
add_action('admin_menu', 'wplalr_login_logout_redirect_menu');
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wplalr_login_logout_action_links' );

add_filter( 'login_redirect', 'wplalr_wp_login_redirect', 9999, 3 );
add_filter( 'logout_redirect', 'wplalr_wp_logout_redirect', 9999, 3 );
add_action( 'admin_enqueue_scripts', 'wplalr_login_logout_redirect_admin_scripts', 10 );

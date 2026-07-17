<?php
/*
Plugin Name: Entryway - WP Login & Logout Redirect
Plugin URI: https://wordpress.org/plugins/wp-login-and-logout-redirect/
Description: Redirect users after login or logout with powerful per-role rules, audit logs, live session management, and email notifications.
Version: 4.0.0
Author: Aminur Islam
Author URI: https://github.com/aminurislamarnob
License: GPLv2 or later
Text Domain: wp-login-logout-redirect
Domain Path: /languages
*/
use PluginizeLab\WpLoginLogoutRedirect\WpLoginLogoutRedirect;

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_LOGIN_LOGOUT_REDIRECT_FILE' ) ) {
    define( 'WP_LOGIN_LOGOUT_REDIRECT_FILE', __FILE__ );
}

if ( ! defined( 'WP_LOGIN_LOGOUT_REDIRECT_BASENAME' ) ) {
    define( 'WP_LOGIN_LOGOUT_REDIRECT_BASENAME', plugin_basename( __FILE__ ) );
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Load Wp_Login_Logout_Redirect Plugin when all plugins loaded
 *
 * @return \PluginizeLab\WpLoginLogoutRedirect\WpLoginLogoutRedirect
 */
function pluginizelab_wp_login_logout_redirect() {
    return WpLoginLogoutRedirect::init();
}

// Lets Go....
pluginizelab_wp_login_logout_redirect();

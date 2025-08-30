<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class Redirection {
	/**
	 * The constructor.
	 */
	public function __construct() {
        add_filter( 'login_redirect', array( $this, 'redirect_after_login' ) );
        add_action('wp_logout', array( $this, 'redirect_after_logout' ) );
    }

    /**
     * Login redirect to user specific URL.
     */
    public function redirect_after_login( $redirect_to ) {
        $redirect_to =  get_option('wplalr_login_redirect');

        if( empty( $redirect_to ) ){
            $redirect_to = admin_url();
        }

        return esc_url( $redirect_to );
    }

    /**
     * Logout redirect to user specific URL.
     */
    public function redirect_after_logout(){
        $wplalr_logout_redirect =  get_option( 'wplalr_logout_redirect' );

        if( empty( $wplalr_logout_redirect ) ){
            $wplalr_logout_redirect = home_url();
        }

        wp_redirect( esc_url( $wplalr_logout_redirect ) );
        exit();
    }
}
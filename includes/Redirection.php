<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performs login/logout redirects, resolved through the rule engine.
 */
class Redirection {

	/**
	 * Rule engine instance.
	 *
	 * @var RuleEngine
	 */
	protected $rule_engine;

	/**
	 * The constructor.
	 *
	 * @param RuleEngine $rule_engine Rule engine instance.
	 */
	public function __construct( RuleEngine $rule_engine ) {
		$this->rule_engine = $rule_engine;

		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'woocommerce_login_redirect' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );
	}

	/**
	 * Filter callback for the core `login_redirect` hook.
	 *
	 * @param string         $redirect_to           The redirect destination URL.
	 * @param string         $requested_redirect_to The requested redirect destination URL.
	 * @param \WP_User|mixed $user                  The logged-in user, or WP_Error.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested_redirect_to = '', $user = null ) {
		return $this->resolve_login_redirect( $redirect_to, $user );
	}

	/**
	 * Filter callback for the `woocommerce_login_redirect` hook.
	 *
	 * @param string         $redirect The redirect destination URL.
	 * @param \WP_User|mixed $user     The logged-in user.
	 * @return string
	 */
	public function woocommerce_login_redirect( $redirect, $user = null ) {
		return $this->resolve_login_redirect( $redirect, $user );
	}

	/**
	 * Resolve and validate the login redirect URL.
	 *
	 * @param string         $fallback The hook's original redirect URL.
	 * @param \WP_User|mixed $user     The logged-in user.
	 * @return string
	 */
	protected function resolve_login_redirect( $fallback, $user ) {
		if ( ! $user instanceof \WP_User ) {
			$user = wp_get_current_user();
		}

		$redirect_to = $this->rule_engine->resolve( 'login', $user );

		if ( empty( $redirect_to ) ) {
			$redirect_to = wp_unslash( get_option( 'wplalr_login_redirect', '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( empty( $redirect_to ) ) {
			$redirect_to = admin_url();
		}

		$safe_fallback = ! empty( $fallback ) ? $fallback : admin_url();

		return wp_validate_redirect( $redirect_to, $safe_fallback );
	}

	/**
	 * Logout redirect to the resolved URL.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 * @return void
	 */
	public function redirect_after_logout( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

		$redirect_to = $this->rule_engine->resolve( 'logout', $user instanceof \WP_User ? $user : null );

		if ( empty( $redirect_to ) ) {
			$redirect_to = get_option( 'wplalr_logout_redirect', '' );
		}

		if ( empty( $redirect_to ) ) {
			$redirect_to = home_url();
		}

		$redirect_url  = esc_url_raw( $redirect_to );
		$redirect_host = wp_parse_url( $redirect_url, PHP_URL_HOST );

		if ( $redirect_host ) {
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $redirect_host ) {
					$hosts[] = $redirect_host;
					return $hosts;
				}
			);
		}

		wp_safe_redirect( $redirect_url );
		exit();
	}
}

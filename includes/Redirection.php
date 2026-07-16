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
	 * Placeholder resolver instance.
	 *
	 * @var Placeholders
	 */
	protected $placeholders;

	/**
	 * The constructor.
	 *
	 * @param RuleEngine   $rule_engine  Rule engine instance.
	 * @param Placeholders $placeholders Placeholder resolver instance.
	 */
	public function __construct( RuleEngine $rule_engine, Placeholders $placeholders ) {
		$this->rule_engine  = $rule_engine;
		$this->placeholders = $placeholders;

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

		$redirect_to = $this->placeholders->replace( $redirect_to, $user );

		if ( empty( $redirect_to ) || $this->is_redirect_loop( $redirect_to, 'login' ) ) {
			$redirect_to = admin_url();
		}

		$safe_fallback = ! empty( $fallback ) ? $fallback : admin_url();

		$redirect_to = wp_validate_redirect( $redirect_to, $safe_fallback );

		$this->announce_destination( $redirect_to, 'login', $user );

		return $redirect_to;
	}

	/**
	 * Announce the settled destination.
	 *
	 * `wplalr_after_resolve` fires from inside the rule engine, so it only ever
	 * carries the rule's own URL — before the option fallback, placeholders and
	 * validation have had their say. Anything that needs to know where the user is
	 * actually going (the audit log) has to be told here instead.
	 *
	 * @param string        $url   The final destination.
	 * @param string        $event 'login' or 'logout'.
	 * @param \WP_User|null $user  The user being redirected.
	 * @return void
	 */
	protected function announce_destination( $url, $event, $user ) {
		/**
		 * Fires once the final redirect destination is settled.
		 *
		 * Unlike `wplalr_after_resolve`, the URL here is the one the user will be
		 * sent to: placeholders expanded, fallbacks applied, validated.
		 *
		 * @param string        $url   The final destination URL.
		 * @param string        $event 'login' or 'logout'.
		 * @param \WP_User|null $user  The user being redirected.
		 */
		do_action( 'wplalr_redirect_resolved', $url, $event, $user instanceof \WP_User ? $user : null );
	}

	/**
	 * Logout redirect to the resolved URL.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 * @return void
	 */
	public function redirect_after_logout( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		$user = $user instanceof \WP_User ? $user : null;

		$redirect_to = $this->rule_engine->resolve( 'logout', $user );

		if ( empty( $redirect_to ) ) {
			$redirect_to = get_option( 'wplalr_logout_redirect', '' );
		}

		$redirect_to = $this->placeholders->replace( $redirect_to, $user );

		if ( empty( $redirect_to ) || $this->is_redirect_loop( $redirect_to, 'logout' ) ) {
			$redirect_to = home_url();
		}

		$redirect_url  = esc_url_raw( $redirect_to );
		$redirect_host = wp_parse_url( $redirect_url, PHP_URL_HOST );

		/**
		 * Whether to allow redirecting to the admin-configured external host.
		 *
		 * Return false to enforce a same-site-only policy on logout.
		 *
		 * @param bool   $allow        Whether to allow the external host.
		 * @param string $redirect_url The resolved logout URL.
		 */
		$allow_external = apply_filters( 'wplalr_allow_external_redirect', true, $redirect_url );

		if ( $redirect_host && $allow_external ) {
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $redirect_host ) {
					$hosts[] = $redirect_host;
					return $hosts;
				}
			);
		}

		$destination = wp_validate_redirect( $redirect_url, home_url() );

		$this->announce_destination( $destination, 'logout', $user );

		wp_safe_redirect( $destination );
		exit();
	}

	/**
	 * Detect a redirect that would loop.
	 *
	 * Guards against sending users back to the login screen on login, or to the
	 * exact URL they are already on.
	 *
	 * @param string $url   The resolved redirect URL.
	 * @param string $event 'login' or 'logout'.
	 * @return bool
	 */
	protected function is_redirect_loop( $url, $event ) {
		if ( empty( $url ) ) {
			return false;
		}

		$target_path = $this->normalize_path( wp_parse_url( $url, PHP_URL_PATH ) );

		if ( '' === $target_path ) {
			return false;
		}

		// On login, never bounce back to the login screen.
		if ( 'login' === $event ) {
			$login_path = $this->normalize_path( wp_parse_url( wp_login_url(), PHP_URL_PATH ) );

			if ( '' !== $login_path && $target_path === $login_path ) {
				return true;
			}
		}

		// Never redirect to the exact URL currently being requested.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current_path = $this->normalize_path(
				wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
			);

			if ( '' !== $current_path && $target_path === $current_path ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a URL path for comparison.
	 *
	 * @param string|null $path The path component.
	 * @return string
	 */
	protected function normalize_path( $path ) {
		if ( empty( $path ) ) {
			return '';
		}

		return untrailingslashit( $path );
	}
}

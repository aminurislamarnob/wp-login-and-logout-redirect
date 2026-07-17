<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class Assets {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_all_scripts' ), 10 );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );
		}
	}

	/**
	 * Register all scripts and styles.
	 *
	 * @return void
	 */
	public function register_all_scripts() {
		$this->register_styles();
		$this->register_scripts();
	}

	/**
	 * Register scripts.
	 *
	 * @return void
	 */
	public function register_scripts() {
		$frontend_script = WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_PUBLIC_ASSET . '/js/script.js';

		wp_register_script( 'wp_login_logout_redirect_script', $frontend_script, array(), WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION, true );
	}

	/**
	 * Register styles.
	 *
	 * @return void
	 */
	public function register_styles() {
		$frontend_style = WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_PUBLIC_ASSET . '/css/style.css';

		wp_register_style( 'wp_login_logout_redirect_style', $frontend_style, array(), WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();

		// One shared bundle drives all three of our screens (Redirects + Rules,
		// Audit Logs, Logged-in Users); the app mounts the matching view.
		if ( ! $screen || ! in_array( $screen->id, Settings::get_page_hooks(), true ) ) {
			return;
		}

		$asset_path = WP_LOGIN_LOGOUT_REDIRECT_DIR . '/assets/build/admin/script.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'wplalr-admin-page',
			WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET . '/build/admin/script.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'wplalr-admin-page', 'wp-login-logout-redirect' );

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$roles = array();
		foreach ( get_editable_roles() as $slug => $details ) {
			$roles[ $slug ] = translate_user_role( $details['name'] );
		}

		wp_localize_script(
			'wplalr-admin-page',
			'wplalrAdmin',
			array(
				'homeUrl'       => home_url(),
				'adminUrl'      => esc_url_raw( admin_url() ),
				'assetsUrl'     => esc_url_raw( WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET ),
				'restRoot'      => esc_url_raw( rest_url() ),
				'roles'         => $roles,
				'currentUserId' => get_current_user_id(),
			)
		);

		wp_enqueue_style(
			'wplalr-admin-styles',
			WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET . '/build/admin/script.css',
			array( 'wp-components' ),
			$asset_file['version']
		);

		wp_enqueue_style( 'wp-components' );
	}

	/**
	 * Enqueue front-end scripts.
	 *
	 * @return void
	 */
	public function enqueue_front_scripts() {
		wp_enqueue_script( 'wp_login_logout_redirect_script' );
		wp_localize_script(
			'wp_login_logout_redirect_script',
			'Wp_Login_Logout_Redirect',
			array()
		);
	}
}

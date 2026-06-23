<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

/**
 * WpLoginLogoutRedirect class
 *
 * @class WpLoginLogoutRedirect The class that holds the entire WpLoginLogoutRedirect plugin
 */
final class WpLoginLogoutRedirect {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '3.1.7';

    /**
     * Instance of self
     *
     * @var WpLoginLogoutRedirect
     */
    private static $instance = null;

    /**
     * Holds various class instances
     *
     * @since 2.6.10
     *
     * @var array
     */
    private $container = [];

    /**
     * Constructor for the WpLoginLogoutRedirect class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( WP_LOGIN_LOGOUT_REDIRECT_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( WP_LOGIN_LOGOUT_REDIRECT_FILE, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
        add_action( 'woocommerce_flush_rewrite_rules', [ $this, 'flush_rewrite_rules' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Initializes the WpLoginLogoutRedirect() class
     *
     * Checks for an existing WpLoginLogoutRedirect instance
     * and if it doesn't find one then create a new one.
     *
     * @return WpLoginLogoutRedirect
     */
    public static function init() {
        if ( self::$instance === null ) {
			self::$instance = new self();
		}

        return self::$instance;
    }

    /**
     * Magic getter to bypass referencing objects
     *
     * @since 2.6.10
     *
     * @param string $prop
     *
     * @return Class Instance
     */
    public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
		}
    }

    /**
     * Placeholder for activation function
     *
     * Nothing is being called here yet.
     */
    public function activate() {
        // Rewrite rules during wp_login_logout_redirect activation
    }

    /**
     * Flush rewrite rules after wp_login_logout_redirect is activated or woocommerce is activated
     *
     * @since 3.2.8
     */
    public function flush_rewrite_rules() {
        // fix rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {     }

    /**
     * Define all constants
     *
     * @return void
     */
    public function define_constants() {
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION', $this->version );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_DIR' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_DIR', dirname( WP_LOGIN_LOGOUT_REDIRECT_FILE ) );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_INC_DIR' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_INC_DIR', WP_LOGIN_LOGOUT_REDIRECT_DIR . '/includes' );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_TEMPLATE_DIR' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_TEMPLATE_DIR', WP_LOGIN_LOGOUT_REDIRECT_DIR . '/templates' );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET', plugins_url( 'assets', WP_LOGIN_LOGOUT_REDIRECT_FILE ) );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ADMIN_ASSET' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ADMIN_ASSET', WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET . '/admin' );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_PUBLIC_ASSET' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_PUBLIC_ASSET', WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ASSET . '/public' );

        // give a way to turn off loading styles and scripts from parent theme
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_LOAD_STYLE' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_LOAD_STYLE', true );
        defined( 'WP_LOGIN_LOGOUT_REDIRECT_LOAD_SCRIPTS' ) || define( 'WP_LOGIN_LOGOUT_REDIRECT_LOAD_SCRIPTS', true );
    }

    /**
     * Load the plugin after WP User Frontend is loaded
     *
     * @return void
     */
    public function init_plugin() {
        $this->includes();
        $this->init_hooks();

        do_action( 'wp_login_logout_redirect_loaded' );
    }

    /**
     * Initialize the actions
     *
     * @return void
     */
    public function init_hooks() {
        // initialize the classes
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'init_classes' ], 4 );
        add_action( 'plugins_loaded', [ $this, 'after_plugins_loaded' ] );
    }

    /**
     * Include all the required files
     *
     * @return void
     */
    public function includes() {
        // include_once STUB_PLUGIN_DIR . '/functions.php';
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-login-logout-redirect', false, WP_LOGIN_LOGOUT_REDIRECT_DIR . '/languages' );
    }

    /**
     * Init all the classes
     *
     * @return void
     */
    public function init_classes() {
        $this->container['scripts'] = new Assets();
        $this->container['admin_settings'] = new Settings();
        $this->container['admin_settings_controller'] = new REST\SettingsController();
        $this->container['redirection'] = new Redirection();
        $this->container['user_login_time'] = new UserLoginTime();
    }

    /**
     * Register plugin REST routes
     *
     * @return void
     */
    public function register_rest_routes() {
        if ( ! isset( $this->container['admin_settings_controller'] ) ) {
            $this->container['admin_settings_controller'] = new REST\SettingsController();
        }
        $this->container['admin_settings_controller']->register_routes();
    }

    /**
     * Executed after all plugins are loaded
     *
     * At this point wp_login_logout_redirect Pro is loaded
     *
     * @since 2.8.7
     *
     * @return void
     */
    public function after_plugins_loaded() {
        // Initiate background processes and other tasks
    }

    /**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WP_LOGIN_LOGOUT_REDIRECT_FILE ) );
	}

    /**
     * Get the template file path to require or include.
     *
     * @param string $name
     * @return string
     */
    public function get_template( $name ) {
        $template = untrailingslashit( WP_LOGIN_LOGOUT_REDIRECT_TEMPLATE_DIR ) . '/' . untrailingslashit( $name );

        return apply_filters( 'wp_login_logout_redirect_template', $template, $name );
    }
}

<?php
/**
 * Shared base for REST controller tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\REST;

use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Boots a REST server with the plugin's routes registered.
 */
abstract class RestTestCase extends TestCase {

	/**
	 * The REST server under test.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Rebuild the REST server so routes pick up filters added by the test.
	 *
	 * Route args are frozen from the item schema at register_routes() time, so a
	 * filter that widens the schema (e.g. a Pro match type) only takes effect if
	 * it is in place before rest_api_init fires.
	 *
	 * @return void
	 */
	protected function reboot_server() {
		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * Sign in as a user with the given role.
	 *
	 * @param string $role Role slug.
	 * @return int The user id.
	 */
	protected function acting_as( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Dispatch a request against the plugin's namespace.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /wplalr/v1, e.g. '/settings'.
	 * @param array  $params Body/query params.
	 * @return \WP_REST_Response
	 */
	protected function dispatch( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/wplalr/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}
}

<?php

namespace PluginizeLab\WpLoginLogoutRedirect\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\WpLoginLogoutRedirect\Sessions\SessionRepository;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * Logged-in users / force-logout REST API controller.
 */
class SessionsController extends WP_REST_Controller {

	/**
	 * Session data layer.
	 *
	 * @var SessionRepository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param SessionRepository|null $repository Data layer.
	 */
	public function __construct( $repository = null ) {
		$this->namespace  = 'wplalr/v1';
		$this->rest_base  = 'sessions';
		$this->repository = $repository instanceof SessionRepository ? $repository : new SessionRepository();
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/destroy-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'destroy_all' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'exclude_self' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-destroy',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_destroy' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'user_ids'     => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'integer' ),
						),
						'exclude_self' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy_user' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'user' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user>\d+)/(?P<token>[A-Za-z0-9]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy_session' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'user'  => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'token' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Capability gate shared by every route.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET a paginated list of users with active sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = $this->repository->query(
			array(
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
				'search'   => (string) $request['search'],
				'role'     => (string) $request['role'],
			)
		);

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (int) $result['total'] );
		$response->header( 'X-WP-TotalPages', (int) $result['pages'] );

		return $response;
	}

	/**
	 * DELETE one session of a user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function destroy_session( $request ) {
		$destroyed = $this->repository->destroy_session( (int) $request['user'], (string) $request['token'] );

		return rest_ensure_response( array( 'destroyed' => $destroyed ) );
	}

	/**
	 * DELETE all of one user's sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function destroy_user( $request ) {
		$destroyed = $this->repository->destroy_user( (int) $request['user'] );

		return rest_ensure_response( array( 'destroyed' => $destroyed ) );
	}

	/**
	 * POST bulk destroy a set of users' sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function bulk_destroy( $request ) {
		$user_ids = array_map( 'absint', (array) $request['user_ids'] );

		if ( $request['exclude_self'] ) {
			$user_ids = array_diff( $user_ids, array( get_current_user_id() ) );
		}

		$affected = $this->repository->destroy_users( $user_ids );

		return rest_ensure_response( array( 'affected' => $affected ) );
	}

	/**
	 * POST destroy every user's sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function destroy_all( $request ) {
		$exclude = $request['exclude_self'] ? array( get_current_user_id() ) : array();

		$affected = $this->repository->destroy_all( $exclude );

		return rest_ensure_response( array( 'affected' => $affected ) );
	}

	/**
	 * Query params for the list endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'role'     => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}

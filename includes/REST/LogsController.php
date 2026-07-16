<?php

namespace PluginizeLab\WpLoginLogoutRedirect\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * Audit-log data REST API controller.
 *
 * Read + delete endpoints for the log table. The two log *settings*
 * (`wplalr_enable_logs`, `wplalr_logs_retention_days`) are folded into the main
 * settings endpoint; this controller serves the log *data*.
 */
class LogsController extends WP_REST_Controller {

	/**
	 * Data layer.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param LogRepository|null $repository Data layer.
	 */
	public function __construct( $repository = null ) {
		$this->namespace  = 'wplalr/v1';
		$this->rest_base  = 'logs';
		$this->repository = $repository instanceof LogRepository ? $repository : new LogRepository();
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
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_all_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'days' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
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
	 * GET a paginated, filtered page of log rows.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'page'     => $request['page'],
			'per_page' => $request['per_page'],
			'event'    => (string) $request['event'],
			'status'   => (string) $request['status'],
			'search'   => (string) $request['search'],
			'orderby'  => (string) $request['orderby'],
			'order'    => (string) $request['order'],
		);

		/**
		 * Filter the log query args before they hit the repository.
		 *
		 * @param array            $args    Query args.
		 * @param \WP_REST_Request $request The request.
		 */
		$args = apply_filters( 'wplalr_rest_logs_query_args', $args, $request );

		$result   = $this->repository->query( $args );
		$response = rest_ensure_response( $result['items'] );

		$response->header( 'X-WP-Total', (int) $result['total'] );
		$response->header( 'X-WP-TotalPages', (int) $result['pages'] );

		return $response;
	}

	/**
	 * GET grouped event counts for the dashboard cards.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_stats( $request ) {
		return rest_ensure_response( $this->repository->stats( (int) $request['days'] ) );
	}

	/**
	 * DELETE a single row.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_item( $request ) {
		$deleted = $this->repository->delete( (int) $request['id'] );

		return rest_ensure_response( array( 'deleted' => $deleted ) );
	}

	/**
	 * DELETE every row.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_all_items( $request ) {
		$this->repository->delete_all();

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Query params for the list endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		/*
		 * Args carrying a `sanitize_callback` need an explicit `validate_callback`
		 * too: WP only falls back to rest_parse_request_arg — which is what
		 * enforces `enum` — for args that define no sanitize_callback of their
		 * own, so otherwise the enums below would never be applied.
		 */
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
			'event'    => array(
				'type'              => 'string',
				'default'           => '',
				'enum'              => array( '', 'login', 'logout', 'failed', 'forced_logout' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_key',
			),
			'status'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby'  => array(
				'type'              => 'string',
				'default'           => 'created_at',
				'enum'              => LogRepository::SORTABLE,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_key',
			),
			'order'    => array(
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
			),
		);
	}
}

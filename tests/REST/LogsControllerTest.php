<?php
/**
 * Audit-log REST endpoint tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\REST;

use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\REST\LogsController
 */
class LogsControllerTest extends RestTestCase {

	/**
	 * Data layer.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->repository = new LogRepository();
	}

	/*
	 * Routing + permissions.
	 */

	public function test_the_log_routes_are_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wplalr/v1/logs', $routes );
		$this->assertArrayHasKey( '/wplalr/v1/logs/stats', $routes );
		$this->assertArrayHasKey( '/wplalr/v1/logs/(?P<id>\d+)', $routes );
	}

	public function test_an_admin_can_read_the_log() {
		$this->acting_as( 'administrator' );

		$this->assertSame( 200, $this->dispatch( 'GET', '/logs' )->get_status() );
	}

	public function test_a_subscriber_cannot_read_the_log() {
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'GET', '/logs' )->get_status() );
	}

	public function test_a_logged_out_visitor_cannot_read_the_log() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( 'GET', '/logs' )->get_status() );
	}

	public function test_a_subscriber_cannot_delete_a_row() {
		$id = $this->repository->insert( array( 'event' => 'login' ) );

		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'DELETE', '/logs/' . $id )->get_status() );
		$this->assertCount( 1, $this->all_log_rows(), 'The row must survive an unauthorized delete.' );
	}

	public function test_a_subscriber_cannot_clear_the_log() {
		$this->repository->insert( array( 'event' => 'login' ) );

		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'DELETE', '/logs' )->get_status() );
		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_a_subscriber_cannot_read_the_stats() {
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'GET', '/logs/stats' )->get_status() );
	}

	/*
	 * Listing.
	 */

	public function test_the_log_lists_rows() {
		$this->acting_as( 'administrator' );

		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		$data = $this->dispatch( 'GET', '/logs' )->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'zoe', $data[0]['username'] );
		$this->assertSame( 'login', $data[0]['event'] );
	}

	public function test_the_list_sends_pagination_headers() {
		$this->acting_as( 'administrator' );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->repository->insert( array( 'event' => 'login' ) );
		}

		$response = $this->dispatch( 'GET', '/logs', array( 'per_page' => 2 ) );
		$headers  = $response->get_headers();

		$this->assertSame( 5, $headers['X-WP-Total'] );
		$this->assertSame( 3, $headers['X-WP-TotalPages'] );
		$this->assertCount( 2, $response->get_data() );
	}

	public function test_the_list_filters_by_event() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'failed' ) );

		$data = $this->dispatch( 'GET', '/logs', array( 'event' => 'failed' ) )->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'failed', $data[0]['event'] );
	}

	public function test_an_event_outside_the_enum_is_rejected() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );

		// A typo'd filter must fail loudly rather than quietly returning every row.
		$response = $this->dispatch( 'GET', '/logs', array( 'event' => 'nonsense' ) );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_every_valid_event_is_accepted() {
		$this->acting_as( 'administrator' );

		foreach ( array( '', 'login', 'logout', 'failed', 'forced_logout' ) as $event ) {
			$this->assertSame(
				200,
				$this->dispatch( 'GET', '/logs', array( 'event' => $event ) )->get_status(),
				sprintf( 'Event "%s" should be accepted.', $event )
			);
		}
	}

	public function test_an_orderby_injection_attempt_is_rejected() {
		$this->acting_as( 'administrator' );

		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		$response = $this->dispatch( 'GET', '/logs', array( 'orderby' => 'id; DROP TABLE wp_users' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertNotEmpty( $this->all_log_rows(), 'The log table must still be intact.' );
	}

	public function test_every_sortable_column_is_accepted() {
		$this->acting_as( 'administrator' );

		foreach ( LogRepository::SORTABLE as $column ) {
			$this->assertSame(
				200,
				$this->dispatch( 'GET', '/logs', array( 'orderby' => $column ) )->get_status(),
				sprintf( 'Column "%s" should be sortable.', $column )
			);
		}
	}

	public function test_an_order_direction_outside_the_enum_is_rejected() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );

		$response = $this->dispatch( 'GET', '/logs', array( 'order' => 'RAND(); DROP TABLE wp_users' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertNotEmpty( $this->all_log_rows() );
	}

	public function test_the_list_searches() {
		$this->acting_as( 'administrator' );

		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'bob',
			)
		);

		$data = $this->dispatch( 'GET', '/logs', array( 'search' => 'zoe' ) )->get_data();

		$this->assertCount( 1, $data );
	}

	public function test_the_list_sorts() {
		$this->acting_as( 'administrator' );

		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'bob',
			)
		);

		$data = $this->dispatch(
			'GET',
			'/logs',
			array(
				'orderby' => 'username',
				'order'   => 'ASC',
			)
		)->get_data();

		$this->assertSame( 'bob', $data[0]['username'] );
	}

	public function test_the_query_args_are_filterable() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'failed' ) );

		add_filter(
			'wplalr_rest_logs_query_args',
			function ( $args ) {
				$args['event'] = 'failed';
				return $args;
			}
		);

		$data = $this->dispatch( 'GET', '/logs' )->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'failed', $data[0]['event'] );
	}

	/*
	 * Stats.
	 */

	public function test_the_stats_endpoint_returns_the_card_counts() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'logout' ) );
		$this->repository->insert( array( 'event' => 'failed' ) );

		$data = $this->dispatch( 'GET', '/logs/stats' )->get_data();

		$this->assertSame( 2, $data['login'] );
		$this->assertSame( 1, $data['logout'] );
		$this->assertSame( 1, $data['failed'] );
		$this->assertSame( 4, $data['total'] );
	}

	public function test_the_stats_endpoint_windows_by_days() {
		$this->acting_as( 'administrator' );

		$this->repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 10 * DAY_IN_SECONDS ) ),
			)
		);
		$this->repository->insert( array( 'event' => 'login' ) );

		$this->assertSame( 1, $this->dispatch( 'GET', '/logs/stats', array( 'days' => 7 ) )->get_data()['login'] );
		$this->assertSame( 2, $this->dispatch( 'GET', '/logs/stats' )->get_data()['login'] );
	}

	/*
	 * Deleting.
	 */

	public function test_an_admin_can_delete_one_row() {
		$this->acting_as( 'administrator' );

		$id = $this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'logout' ) );

		$response = $this->dispatch( 'DELETE', '/logs/' . $id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_deleting_a_missing_row_reports_false() {
		$this->acting_as( 'administrator' );

		$this->assertFalse( $this->dispatch( 'DELETE', '/logs/999999' )->get_data()['deleted'] );
	}

	public function test_an_admin_can_clear_the_whole_log() {
		$this->acting_as( 'administrator' );

		$this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'logout' ) );

		$response = $this->dispatch( 'DELETE', '/logs' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertCount( 0, $this->all_log_rows() );
	}

	public function test_a_non_numeric_row_id_does_not_match_the_route() {
		$this->acting_as( 'administrator' );

		$this->assertSame( 404, $this->dispatch( 'DELETE', '/logs/abc' )->get_status() );
	}
}

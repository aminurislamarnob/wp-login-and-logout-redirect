<?php
/**
 * Audit-log data layer tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Logs;

use PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\LogRepository
 */
class LogRepositoryTest extends TestCase {

	/**
	 * Subject under test.
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
	 * insert().
	 */

	public function test_insert_returns_the_new_row_id() {
		$id = $this->repository->insert(
			array(
				'username' => 'zoe',
				'event'    => 'login',
			)
		);

		$this->assertGreaterThan( 0, $id );
	}

	public function test_insert_persists_the_given_fields() {
		$this->repository->insert(
			array(
				'user_id'      => 42,
				'username'     => 'zoe',
				'event'        => 'login',
				'status'       => 'success',
				'redirect_url' => 'https://example.test/hi/',
				'rule_id'      => 'rule-1',
				'ip'           => '203.0.113.9',
				'browser'      => 'Chrome',
				'device_os'    => 'macOS',
			)
		);

		$rows = $this->all_log_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( '42', (string) $rows[0]['user_id'] );
		$this->assertSame( 'zoe', $rows[0]['username'] );
		$this->assertSame( 'login', $rows[0]['event'] );
		$this->assertSame( 'https://example.test/hi/', $rows[0]['redirect_url'] );
		$this->assertSame( 'rule-1', $rows[0]['rule_id'] );
		$this->assertSame( '203.0.113.9', $rows[0]['ip'] );
	}

	public function test_insert_defaults_status_to_success() {
		$this->repository->insert( array( 'event' => 'login' ) );

		$rows = $this->all_log_rows();

		$this->assertSame( 'success', $rows[0]['status'] );
	}

	public function test_insert_stamps_created_at_when_omitted() {
		$this->repository->insert( array( 'event' => 'login' ) );

		$rows = $this->all_log_rows();

		$this->assertNotEmpty( $rows[0]['created_at'] );
	}

	public function test_insert_sanitizes_the_username() {
		$this->repository->insert(
			array(
				'username' => '<script>alert(1)</script>zoe',
				'event'    => 'login',
			)
		);

		$rows = $this->all_log_rows();

		$this->assertSame( 'zoe', $rows[0]['username'] );
	}

	public function test_insert_normalizes_the_event_to_a_key() {
		$this->repository->insert( array( 'event' => 'Login Event!' ) );

		$rows = $this->all_log_rows();

		$this->assertSame( 'loginevent', $rows[0]['event'] );
	}

	public function test_insert_stores_null_for_an_empty_redirect_url() {
		$this->repository->insert(
			array(
				'event'        => 'login',
				'redirect_url' => '',
			)
		);

		$rows = $this->all_log_rows();

		$this->assertNull( $rows[0]['redirect_url'] );
	}

	/*
	 * get().
	 */

	public function test_get_returns_a_prepared_row() {
		$id = $this->repository->insert(
			array(
				'username' => 'zoe',
				'event'    => 'login',
				'ip'       => '203.0.113.9',
			)
		);

		$row = $this->repository->get( $id );

		$this->assertSame( $id, $row['id'] );
		$this->assertSame( 'zoe', $row['username'] );
		$this->assertNotEmpty( $row['time_diff'] );
	}

	public function test_get_returns_null_for_a_missing_row() {
		$this->assertNull( $this->repository->get( 999999 ) );
	}

	public function test_get_returns_null_for_an_invalid_id() {
		$this->assertNull( $this->repository->get( 0 ) );
		$this->assertNull( $this->repository->get( -5 ) );
	}

	public function test_prepared_row_casts_types_and_is_filterable() {
		$id = $this->repository->insert(
			array(
				'event'   => 'login',
				'user_id' => 7,
			)
		);

		$row = $this->repository->get( $id );

		$this->assertIsInt( $row['id'] );
		$this->assertSame( 7, $row['user_id'] );
		$this->assertIsString( $row['redirect_url'] );

		add_filter(
			'wplalr_rest_log_item',
			function ( $item ) {
				$item['country'] = 'NL';
				return $item;
			}
		);

		$this->assertSame( 'NL', $this->repository->get( $id )['country'] );
	}

	public function test_prepared_row_reports_a_zero_user_id_for_a_null_column() {
		$id = $this->repository->insert( array( 'event' => 'failed' ) );

		$this->assertSame( 0, $this->repository->get( $id )['user_id'] );
	}

	/*
	 * query().
	 */

	public function test_query_returns_items_total_and_pages() {
		$this->seed( 3, 'login' );

		$result = $this->repository->query();

		$this->assertCount( 3, $result['items'] );
		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 1, $result['pages'] );
	}

	public function test_query_paginates() {
		$this->seed( 5, 'login' );

		$page_one = $this->repository->query(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);
		$page_three = $this->repository->query(
			array(
				'per_page' => 2,
				'page'     => 3,
			)
		);

		$this->assertCount( 2, $page_one['items'] );
		$this->assertCount( 1, $page_three['items'] );
		$this->assertSame( 5, $page_one['total'] );
		$this->assertSame( 3, $page_one['pages'] );
	}

	public function test_query_clamps_per_page_to_100() {
		$this->seed( 2, 'login' );

		$result = $this->repository->query( array( 'per_page' => 5000 ) );

		// 2 rows over a clamped page size of 100 is still a single page.
		$this->assertSame( 1, $result['pages'] );
	}

	public function test_query_filters_by_event() {
		$this->seed( 2, 'login' );
		$this->seed( 3, 'failed' );

		$result = $this->repository->query( array( 'event' => 'failed' ) );

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 'failed', $result['items'][0]['event'] );
	}

	public function test_query_ignores_an_unknown_event_filter() {
		$this->seed( 2, 'login' );

		$result = $this->repository->query( array( 'event' => 'bogus' ) );

		$this->assertSame( 2, $result['total'], 'An unknown event must not silently filter everything out.' );
	}

	public function test_query_filters_by_status() {
		$this->repository->insert(
			array(
				'event'  => 'login',
				'status' => 'success',
			)
		);
		$this->repository->insert(
			array(
				'event'  => 'failed',
				'status' => 'failed',
			)
		);

		$result = $this->repository->query( array( 'status' => 'failed' ) );

		$this->assertSame( 1, $result['total'] );
	}

	public function test_query_searches_username_and_ip() {
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
				'ip'       => '203.0.113.9',
			)
		);
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'bob',
				'ip'       => '198.51.100.4',
			)
		);

		$this->assertSame( 1, $this->repository->query( array( 'search' => 'zoe' ) )['total'] );
		$this->assertSame( 1, $this->repository->query( array( 'search' => '198.51' ) )['total'] );
		$this->assertSame( 0, $this->repository->query( array( 'search' => 'nobody' ) )['total'] );
	}

	public function test_search_treats_wildcards_literally() {
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		// A bare % must not match every row.
		$this->assertSame( 0, $this->repository->query( array( 'search' => '%' ) )['total'] );
	}

	public function test_query_orders_by_a_sortable_column() {
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'bob',
			)
		);
		$this->repository->insert(
			array(
				'event'    => 'login',
				'username' => 'zoe',
			)
		);

		$asc = $this->repository->query(
			array(
				'orderby' => 'username',
				'order'   => 'ASC',
			)
		);

		$this->assertSame( 'bob', $asc['items'][0]['username'] );

		$desc = $this->repository->query(
			array(
				'orderby' => 'username',
				'order'   => 'DESC',
			)
		);

		$this->assertSame( 'zoe', $desc['items'][0]['username'] );
	}

	public function test_query_rejects_an_orderby_outside_the_allowlist() {
		$this->seed( 2, 'login' );

		// An injection attempt must fall back to created_at rather than error.
		$result = $this->repository->query( array( 'orderby' => 'id; DROP TABLE users' ) );

		$this->assertSame( 2, $result['total'] );
	}

	public function test_query_defaults_to_newest_first() {
		$older = $this->repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'older',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);
		$newer = $this->repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'newer',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$result = $this->repository->query();

		$this->assertSame( 'newer', $result['items'][0]['username'] );
	}

	/*
	 * delete().
	 */

	public function test_delete_removes_one_row() {
		$id = $this->repository->insert( array( 'event' => 'login' ) );
		$this->repository->insert( array( 'event' => 'logout' ) );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertCount( 1, $this->all_log_rows() );
	}

	public function test_delete_reports_false_for_a_missing_row() {
		$this->assertFalse( $this->repository->delete( 999999 ) );
	}

	public function test_delete_all_empties_the_table() {
		$this->seed( 3, 'login' );

		$this->repository->delete_all();

		$this->assertCount( 0, $this->all_log_rows() );
	}

	/*
	 * purge_older_than().
	 */

	public function test_purge_removes_only_rows_past_the_window() {
		$this->repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'ancient',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 40 * DAY_IN_SECONDS ) ),
			)
		);
		$this->repository->insert(
			array(
				'event'      => 'login',
				'username'   => 'recent',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) ),
			)
		);

		$deleted = $this->repository->purge_older_than( 30 );

		$rows = $this->all_log_rows();

		$this->assertSame( 1, $deleted );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'recent', $rows[0]['username'] );
	}

	public function test_purge_is_a_noop_for_a_zero_window() {
		$this->seed( 2, 'login' );

		$this->assertSame( 0, $this->repository->purge_older_than( 0 ) );
		$this->assertCount( 2, $this->all_log_rows(), 'A retention of 0 means keep forever.' );
	}

	/*
	 * stats().
	 */

	public function test_stats_counts_each_event() {
		$this->seed( 2, 'login' );
		$this->seed( 3, 'logout' );
		$this->seed( 4, 'failed' );

		$stats = $this->repository->stats();

		$this->assertSame( 2, $stats['login'] );
		$this->assertSame( 3, $stats['logout'] );
		$this->assertSame( 4, $stats['failed'] );
		$this->assertSame( 9, $stats['total'] );
	}

	public function test_stats_totals_include_events_without_a_card() {
		$this->seed( 2, 'login' );
		$this->seed( 1, 'forced_logout' );

		$stats = $this->repository->stats();

		$this->assertSame( 2, $stats['login'] );
		$this->assertSame( 3, $stats['total'], 'forced_logout has no card but still counts toward the total.' );
	}

	public function test_stats_are_zero_for_an_empty_table() {
		$this->assertSame(
			array(
				'login'  => 0,
				'logout' => 0,
				'failed' => 0,
				'total'  => 0,
			),
			$this->repository->stats()
		);
	}

	public function test_stats_windows_by_days() {
		$this->repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 10 * DAY_IN_SECONDS ) ),
			)
		);
		$this->repository->insert(
			array(
				'event'      => 'login',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);

		$this->assertSame( 1, $this->repository->stats( 7 )['login'] );
		$this->assertSame( 2, $this->repository->stats( 0 )['login'], '0 days means all time.' );
	}

	/**
	 * Insert N rows of one event.
	 *
	 * @param int    $count How many.
	 * @param string $event Event slug.
	 * @return void
	 */
	protected function seed( $count, $event ) {
		for ( $i = 0; $i < $count; $i++ ) {
			$this->repository->insert(
				array(
					'event'    => $event,
					'username' => $event . '-' . $i,
				)
			);
		}
	}
}

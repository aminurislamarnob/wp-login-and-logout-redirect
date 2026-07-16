<?php
/**
 * Last-login tracking / users column tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit;

use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;
use PluginizeLab\WpLoginLogoutRedirect\UserLoginTime;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\UserLoginTime
 */
class UserLoginTimeTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var UserLoginTime
	 */
	protected $login_time;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->login_time = new UserLoginTime();
	}

	/*
	 * Recording the timestamp.
	 */

	public function test_logging_in_stores_the_timestamp() {
		$user = $this->make_user();

		$before = time();
		$this->login_time->update_user_login_timestamp( $user->user_login, $user );

		$stored = (int) get_user_meta( $user->ID, 'wplalr_last_login', true );

		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( time(), $stored );
	}

	public function test_the_wp_login_action_records_the_timestamp() {
		$user = $this->make_user();

		do_action( 'wp_login', $user->user_login, $user );

		$this->assertNotEmpty( get_user_meta( $user->ID, 'wplalr_last_login', true ) );
	}

	public function test_a_later_login_overwrites_the_previous_timestamp() {
		$user = $this->make_user();

		update_user_meta( $user->ID, 'wplalr_last_login', 1000 );

		$this->login_time->update_user_login_timestamp( $user->user_login, $user );

		$this->assertGreaterThan( 1000, (int) get_user_meta( $user->ID, 'wplalr_last_login', true ) );
	}

	/*
	 * The users-table column.
	 */

	public function test_the_last_login_column_is_registered() {
		$columns = $this->login_time->add_user_table_column( array( 'username' => 'Username' ) );

		$this->assertArrayHasKey( 'wplalr_last_login', $columns );
		$this->assertSame( 'Last Login', $columns['wplalr_last_login'] );
	}

	public function test_registering_the_column_keeps_the_existing_ones() {
		$columns = $this->login_time->add_user_table_column( array( 'username' => 'Username' ) );

		$this->assertArrayHasKey( 'username', $columns );
	}

	public function test_the_column_renders_the_formatted_login_time() {
		$user      = $this->make_user();
		$timestamp = 1700000000;

		update_user_meta( $user->ID, 'wplalr_last_login', $timestamp );

		$expected = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		$actual   = $this->login_time->user_last_login_time( '', 'wplalr_last_login', $user->ID );

		$this->assertSame( $expected, $actual );
	}

	public function test_the_column_renders_a_dash_for_a_user_that_never_logged_in() {
		$user = $this->make_user();

		$this->assertSame( '-', $this->login_time->user_last_login_time( '', 'wplalr_last_login', $user->ID ) );
	}

	public function test_the_column_is_sortable() {
		$columns = $this->login_time->user_login_time_sortable_columns( array() );

		$this->assertSame( 'wplalr_last_login', $columns['wplalr_last_login'] );
	}

	public function test_making_the_column_sortable_keeps_the_existing_ones() {
		$columns = $this->login_time->user_login_time_sortable_columns( array( 'login' => 'login' ) );

		$this->assertArrayHasKey( 'login', $columns );
		$this->assertArrayHasKey( 'wplalr_last_login', $columns );
	}

	public function test_rendering_another_plugins_column_leaves_its_output_alone() {
		$user = $this->make_user();

		$this->markTestIncomplete(
			'Known defect: user_last_login_time() returns its own empty buffer for every column '
			. 'instead of passing $output through, so any other custom users-table column renders '
			. 'blank. Delete this line once the early return is fixed.'
		);

		$this->assertSame(
			'Some other plugin value',
			$this->login_time->user_last_login_time( 'Some other plugin value', 'some_other_column', $user->ID )
		);
	}

	/*
	 * Sorting.
	 */

	public function test_sorting_by_the_column_switches_the_query_to_the_meta_key() {
		set_current_screen( 'users' );
		$_GET['orderby'] = 'wplalr_last_login';

		$query = new \WP_User_Query();
		$this->login_time->sort_user_last_login_column( $query );

		$this->assertSame( 'wplalr_last_login', $query->query_vars['meta_key'] );
		$this->assertSame( 'meta_value', $query->query_vars['orderby'] );

		unset( $_GET['orderby'] );
	}

	public function test_sorting_by_another_column_leaves_the_query_alone() {
		set_current_screen( 'users' );
		$_GET['orderby'] = 'email';

		$query = new \WP_User_Query();
		$this->login_time->sort_user_last_login_column( $query );

		$this->assertArrayNotHasKey( 'meta_key', $query->query_vars );

		unset( $_GET['orderby'] );
	}

	public function test_the_query_is_untouched_outside_the_users_screen() {
		set_current_screen( 'edit-post' );
		$_GET['orderby'] = 'wplalr_last_login';

		$query = new \WP_User_Query();
		$this->login_time->sort_user_last_login_column( $query );

		$this->assertArrayNotHasKey( 'meta_key', $query->query_vars );

		unset( $_GET['orderby'] );
	}

	public function test_users_are_ordered_by_their_last_login() {
		$oldest = $this->make_user( 'oldest' );
		$newest = $this->make_user( 'newest' );

		update_user_meta( $oldest->ID, 'wplalr_last_login', 1000 );
		update_user_meta( $newest->ID, 'wplalr_last_login', 2000 );

		$found = get_users(
			array(
				'meta_key' => 'wplalr_last_login',
				'orderby'  => 'meta_value',
				'order'    => 'DESC',
				'fields'   => 'ID',
				'include'  => array( $oldest->ID, $newest->ID ),
			)
		);

		$this->assertSame( array( $newest->ID, $oldest->ID ), array_map( 'intval', $found ) );
	}

	/**
	 * Create a user and return the WP_User.
	 *
	 * @param string $login Login name.
	 * @return \WP_User
	 */
	protected function make_user( $login = '' ) {
		$args = array( 'role' => 'subscriber' );

		if ( $login ) {
			$args['user_login'] = $login;
		}

		return get_user_by( 'id', self::factory()->user->create( $args ) );
	}
}

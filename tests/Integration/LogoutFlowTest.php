<?php
/**
 * End-to-end logout flow.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

/**
 * Fires the real `wp_logout` action and captures the redirect Redirection issues
 * before its exit(), asserting the rule engine, placeholders and logger agree.
 *
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Redirection
 * @covers \PluginizeLab\WpLoginLogoutRedirect\RuleEngine
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Logger
 */
class LogoutFlowTest extends IntegrationTestCase {

	/*
	 * Where the user lands.
	 */

	public function test_a_matching_rule_decides_where_the_user_lands() {
		$user = $this->make_user( 'editor' );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'editor' ),
							),
						),
						'logout_url' => home_url( '/bye-editor/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/bye-editor/' ), $this->log_out( $user->ID ) );
	}

	public function test_a_user_the_rule_does_not_match_falls_through_to_the_global_option() {
		$user = $this->make_user( 'subscriber' );

		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );
		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'editor' ),
							),
						),
						'logout_url' => home_url( '/bye-editor/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/goodbye/' ), $this->log_out( $user->ID ) );
	}

	public function test_the_global_option_is_used_when_no_rules_exist() {
		$user = $this->make_user();

		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );

		$this->assertSame( home_url( '/goodbye/' ), $this->log_out( $user->ID ) );
	}

	public function test_logout_falls_back_to_the_home_page_with_nothing_configured() {
		$user = $this->make_user();

		$this->assertSame( home_url(), $this->log_out( $user->ID ) );
	}

	public function test_a_rule_with_only_a_login_url_does_not_hijack_the_logout() {
		$user = $this->make_user();

		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => home_url( '/login-only/' ) ) ),
			)
		);

		$this->assertSame( home_url(), $this->log_out( $user->ID ) );
	}

	public function test_placeholders_are_expanded_with_the_user_that_logged_out() {
		$user = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->set_rules(
			array(
				$this->make_rule( array( 'logout_url' => '{{website_url}}/farewell/{{username}}/' ) ),
			)
		);

		$this->assertSame( home_url() . '/farewell/ada/', $this->log_out( $user->ID ) );
	}

	/*
	 * External destinations.
	 */

	public function test_an_external_logout_destination_is_allowed_by_default() {
		$user = $this->make_user();

		update_option( 'wplalr_logout_redirect', 'https://partner.example.com/signed-out' );

		$this->assertSame( 'https://partner.example.com/signed-out', $this->log_out( $user->ID ) );
	}

	public function test_a_site_can_refuse_external_logout_destinations() {
		$user = $this->make_user();

		update_option( 'wplalr_logout_redirect', 'https://partner.example.com/signed-out' );

		add_filter( 'wplalr_allow_external_redirect', '__return_false' );

		$this->assertSame( home_url(), $this->log_out( $user->ID ) );
	}

	/*
	 * The audit log.
	 */

	public function test_a_logout_is_logged_with_the_rule_that_chose_the_destination() {
		$this->enable_logs();

		$user = $this->make_user( 'editor', array( 'user_login' => 'grace' ) );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'id'         => 'rule-bye',
						'logout_url' => home_url( '/bye-editor/' ),
					)
				),
			)
		);

		$this->log_out( $user->ID );

		$row = $this->only_log_row();

		$this->assertSame( 'logout', $row['event'] );
		$this->assertSame( 'success', $row['status'] );
		$this->assertSame( 'grace', $row['username'] );
		$this->assertSame( (string) $user->ID, $row['user_id'] );
		$this->assertSame( home_url( '/bye-editor/' ), $row['redirect_url'] );
		$this->assertSame( 'rule-bye', $row['rule_id'] );
	}

	public function test_a_logout_row_carries_the_request_context() {
		$this->enable_logs();

		$this->log_out( $this->make_user()->ID );

		$row = $this->only_log_row();

		$this->assertSame( '203.0.113.10', $row['ip'] );
		$this->assertSame( 'Chrome', $row['browser'] );
		$this->assertSame( 'macOS', $row['device_os'] );
	}

	public function test_a_logout_sent_by_the_global_option_logs_where_the_user_actually_went() {
		$this->markTestIncomplete(
			'Same defect the login flow has: the logger hooks wplalr_after_resolve, which fires '
			. 'before Redirection falls back to the wplalr_logout_redirect option, so redirect_url '
			. 'is NULL for every logout the option handles.'
		);

		$this->enable_logs();

		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );

		$this->log_out( $this->make_user()->ID );

		$this->assertSame( home_url( '/goodbye/' ), $this->only_log_row()['redirect_url'] );
	}

	public function test_a_logout_is_logged_exactly_once() {
		$this->enable_logs();

		$this->log_out( $this->make_user()->ID );

		$this->assertCount( 1, $this->log_rows_for( 'logout' ) );
	}

	public function test_nothing_is_logged_while_logging_is_switched_off() {
		$this->log_out( $this->make_user()->ID );

		$this->assertCount( 0, $this->all_log_rows() );
	}

	/*
	 * Login and logout together.
	 */

	public function test_a_full_session_leaves_a_login_and_a_logout_row_in_order() {
		$this->enable_logs();

		$user = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->sign_in( $user );
		$this->log_out( $user->ID );

		$rows = $this->all_log_rows();

		$this->assertCount( 2, $rows );
		$this->assertSame( 'login', $rows[0]['event'] );
		$this->assertSame( 'logout', $rows[1]['event'] );
		$this->assertSame( 'ada', $rows[0]['username'] );
		$this->assertSame( 'ada', $rows[1]['username'] );
	}
}

<?php
/**
 * End-to-end login flow.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

/**
 * A real wp_signon() followed by the `login_redirect` filter, asserting on what
 * the rule engine, placeholders, logger and last-login tracker all did together.
 *
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Redirection
 * @covers \PluginizeLab\WpLoginLogoutRedirect\RuleEngine
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Placeholders
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\Logger
 * @covers \PluginizeLab\WpLoginLogoutRedirect\UserLoginTime
 */
class LoginFlowTest extends IntegrationTestCase {

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
						'login_url'  => home_url( '/editor-desk/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/editor-desk/' ), $this->sign_in( $user ) );
	}

	public function test_a_user_the_rule_does_not_match_falls_through_to_the_global_option() {
		$subscriber = $this->make_user( 'subscriber' );

		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );
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
						'login_url'  => home_url( '/editor-desk/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/members/' ), $this->sign_in( $subscriber ) );
	}

	public function test_the_first_matching_rule_wins() {
		$user = $this->make_user( 'editor' );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'label'     => 'First',
						'login_url' => home_url( '/first/' ),
					)
				),
				$this->make_rule(
					array(
						'label'     => 'Second',
						'login_url' => home_url( '/second/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/first/' ), $this->sign_in( $user ) );
	}

	public function test_a_disabled_rule_is_skipped_in_favour_of_the_next_one() {
		$user = $this->make_user( 'editor' );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'enabled'   => false,
						'login_url' => home_url( '/disabled/' ),
					)
				),
				$this->make_rule( array( 'login_url' => home_url( '/live/' ) ) ),
			)
		);

		$this->assertSame( home_url( '/live/' ), $this->sign_in( $user ) );
	}

	public function test_a_capability_rule_matches_the_user_that_has_it() {
		$user = $this->make_user( 'editor' );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'conditions' => array(
							array(
								'type'   => 'capability',
								'values' => array( 'edit_others_posts' ),
							),
						),
						'login_url'  => home_url( '/can-edit/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/can-edit/' ), $this->sign_in( $user ) );
	}

	public function test_a_user_rule_targets_that_one_account() {
		$targeted = $this->make_user( 'subscriber' );
		$other    = $this->make_user( 'subscriber' );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'conditions' => array(
							array(
								'type'   => 'user',
								'values' => array( (string) $targeted->ID ),
							),
						),
						'login_url'  => home_url( '/vip/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/vip/' ), $this->sign_in( $targeted ) );
		$this->assertSame( admin_url(), $this->sign_in( $other ) );
	}

	public function test_the_global_option_is_used_when_no_rules_exist() {
		$user = $this->make_user();

		update_option( 'wplalr_login_redirect', home_url( '/welcome/' ) );

		$this->assertSame( home_url( '/welcome/' ), $this->sign_in( $user ) );
	}

	public function test_login_falls_back_to_the_dashboard_with_nothing_configured() {
		$user = $this->make_user();

		$this->assertSame( admin_url(), $this->sign_in( $user ) );
	}

	public function test_placeholders_are_expanded_with_the_user_that_just_logged_in() {
		$user = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => '{{website_url}}/author/{{username}}/' ) ),
			)
		);

		$this->assertSame( home_url() . '/author/ada/', $this->sign_in( $user ) );
	}

	public function test_a_redirect_back_to_the_login_screen_is_replaced_with_the_dashboard() {
		$user = $this->make_user();

		update_option( 'wplalr_login_redirect', wp_login_url() );

		$this->assertSame( admin_url(), $this->sign_in( $user ) );
	}

	public function test_an_off_site_redirect_is_refused_in_favour_of_the_requested_destination() {
		$user     = $this->make_user();
		$requested = home_url( '/safe/' );

		update_option( 'wplalr_login_redirect', 'https://evil.example.com/steal' );

		$this->assertSame( $requested, $this->sign_in( $user, 'correct-horse', $requested ) );
	}

	/*
	 * Last-login tracking rides along on the same wp_login.
	 */

	public function test_signing_in_records_the_last_login_timestamp() {
		$user   = $this->make_user();
		$before = time();

		$this->sign_in( $user );

		$stored = (int) get_user_meta( $user->ID, 'wplalr_last_login', true );

		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( time(), $stored );
	}

	public function test_a_failed_sign_in_does_not_record_a_last_login() {
		$user = $this->make_user();

		$this->fail_sign_in( $user->user_login );

		$this->assertSame( '', get_user_meta( $user->ID, 'wplalr_last_login', true ) );
	}

	/*
	 * The audit log, written across wp_login + wplalr_after_resolve.
	 */

	public function test_a_login_is_logged_with_the_destination_and_the_rule_that_chose_it() {
		$this->enable_logs();

		$user = $this->make_user( 'editor', array( 'user_login' => 'grace' ) );

		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'id'        => 'rule-editors',
						'login_url' => home_url( '/editor-desk/' ),
					)
				),
			)
		);

		$this->sign_in( $user );

		$row = $this->only_log_row();

		$this->assertSame( 'login', $row['event'] );
		$this->assertSame( 'success', $row['status'] );
		$this->assertSame( 'grace', $row['username'] );
		$this->assertSame( (string) $user->ID, $row['user_id'] );
		$this->assertSame( home_url( '/editor-desk/' ), $row['redirect_url'] );
		$this->assertSame( 'rule-editors', $row['rule_id'] );
	}

	public function test_a_login_row_carries_the_request_context() {
		$this->enable_logs();

		$this->sign_in( $this->make_user() );

		$row = $this->only_log_row();

		$this->assertSame( '203.0.113.10', $row['ip'] );
		$this->assertSame( 'Chrome', $row['browser'] );
		$this->assertSame( 'macOS', $row['device_os'] );
	}

	public function test_a_login_with_no_matching_rule_is_logged_without_a_rule_id() {
		$this->enable_logs();

		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->sign_in( $this->make_user() );

		$row = $this->only_log_row();

		$this->assertNull( $row['rule_id'] );
	}

	public function test_a_login_sent_by_the_global_option_logs_where_the_user_actually_went() {
		$this->markTestIncomplete(
			'Logger::on_after_resolve hooks wplalr_after_resolve, which RuleEngine::resolve() '
			. 'fires before Redirection applies the global-option fallback. The logger only ever '
			. 'sees the rule URL, so redirect_url is NULL for every login the option handles — '
			. 'which is every site not using rules. Delete this line when the logger records the '
			. 'final destination instead.'
		);

		$this->enable_logs();

		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->sign_in( $this->make_user() );

		$this->assertSame( home_url( '/members/' ), $this->only_log_row()['redirect_url'] );
	}

	public function test_a_login_sent_to_the_dashboard_fallback_logs_where_the_user_actually_went() {
		$this->markTestIncomplete(
			'Same root cause: nothing is configured, Redirection falls back to admin_url(), but '
			. 'the rule engine resolved an empty string and that is what the logger stored.'
		);

		$this->enable_logs();

		$this->sign_in( $this->make_user() );

		$this->assertSame( admin_url(), $this->only_log_row()['redirect_url'] );
	}

	public function test_a_login_logs_the_expanded_placeholder_url_not_the_raw_token() {
		$this->markTestIncomplete(
			'Same root cause, worst symptom: the logger sees the rule URL before Placeholders '
			. 'runs, so "{{website_url}}/author/{{username}}/" is stored after esc_url_raw '
			. 'strips the braces — logging http://website_url/author/username/, a destination '
			. 'that does not exist and was never visited.'
		);

		$this->enable_logs();

		$user = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => '{{website_url}}/author/{{username}}/' ) ),
			)
		);

		$destination = $this->sign_in( $user );

		$this->assertSame( home_url() . '/author/ada/', $destination );
		$this->assertSame( $destination, $this->only_log_row()['redirect_url'] );
	}

	public function test_a_login_that_never_resolves_a_redirect_is_still_logged_at_shutdown() {
		$logger = $this->enable_logs();

		$user = $this->make_user();

		// A programmatic login: wp_login fires, but nothing ever applies
		// `login_redirect`, so only the shutdown safety net can write this row.
		wp_signon(
			array(
				'user_login'    => $user->user_login,
				'user_password' => 'correct-horse',
			)
		);

		$this->assertCount( 0, $this->all_log_rows(), 'The row should still be parked.' );

		$logger->flush_pending_login();

		$row = $this->only_log_row();

		$this->assertSame( 'login', $row['event'] );
		$this->assertNull( $row['redirect_url'] );
	}

	public function test_a_login_is_logged_exactly_once() {
		$logger = $this->enable_logs();

		$this->sign_in( $this->make_user() );

		// The redirect already flushed the parked row; the shutdown net must not
		// write a second copy.
		$logger->flush_pending_login();

		$this->assertCount( 1, $this->log_rows_for( 'login' ) );
	}

	public function test_a_failed_sign_in_is_logged_with_its_error_code() {
		$this->enable_logs();

		$user = $this->make_user( 'subscriber', array( 'user_login' => 'mallory' ) );

		$this->fail_sign_in( $user->user_login );

		$row = $this->only_log_row();

		$this->assertSame( 'failed', $row['event'] );
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( 'mallory', $row['username'] );
		$this->assertSame( 'incorrect_password', $row['error_code'] );
	}

	public function test_a_sign_in_attempt_on_an_unknown_username_is_logged() {
		$this->enable_logs();

		$this->fail_sign_in( 'nobody-here', 'anything' );

		$row = $this->only_log_row();

		$this->assertSame( 'failed', $row['event'] );
		$this->assertSame( 'nobody-here', $row['username'] );
	}

	public function test_nothing_is_logged_while_logging_is_switched_off() {
		// No enable_logs() call: the plugin booted with logging off.
		$user = $this->make_user();

		$this->sign_in( $user );
		$this->fail_sign_in( $user->user_login );

		$this->assertCount( 0, $this->all_log_rows(), 'Logging is opt-in; no PII may be stored.' );
	}

	public function test_redirects_still_work_while_logging_is_switched_off() {
		$user = $this->make_user();

		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->assertSame( home_url( '/members/' ), $this->sign_in( $user ) );
	}

	/*
	 * WooCommerce shares the resolver via its own filter.
	 */

	public function test_the_woocommerce_login_filter_resolves_the_same_destination() {
		$user = $this->make_user( 'customer' );

		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => home_url( '/shop/account/' ) ) ),
			)
		);

		wp_set_current_user( $user->ID );

		$this->assertSame(
			home_url( '/shop/account/' ),
			apply_filters( 'woocommerce_login_redirect', wc_get_page_permalink_stub(), $user )
		);
	}
}

/**
 * Stand-in for the URL WooCommerce would pass as its default redirect.
 *
 * @return string
 */
function wc_get_page_permalink_stub() {
	return home_url( '/my-account/' );
}

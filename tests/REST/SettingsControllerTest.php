<?php
/**
 * Settings REST endpoint tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\REST;

use PluginizeLab\WpLoginLogoutRedirect\RuleEngine;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\REST\SettingsController
 */
class SettingsControllerTest extends RestTestCase {

	/*
	 * Routing + permissions.
	 */

	public function test_the_settings_route_is_registered() {
		$this->assertArrayHasKey( '/wplalr/v1/settings', $this->server->get_routes() );
	}

	public function test_an_admin_can_read_the_settings() {
		$this->acting_as( 'administrator' );

		$this->assertSame( 200, $this->dispatch( 'GET', '/settings' )->get_status() );
	}

	public function test_a_subscriber_cannot_read_the_settings() {
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->dispatch( 'GET', '/settings' )->get_status() );
	}

	public function test_a_logged_out_visitor_cannot_read_the_settings() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( 'GET', '/settings' )->get_status() );
	}

	public function test_a_subscriber_cannot_write_the_settings() {
		$this->acting_as( 'subscriber' );

		$response = $this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://evil.test/' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( '', get_option( 'wplalr_login_redirect', '' ) );
	}

	public function test_an_editor_cannot_write_the_settings() {
		$this->acting_as( 'editor' );

		$this->assertSame( 403, $this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://evil.test/' ) )->get_status() );
	}

	/*
	 * Reading.
	 */

	public function test_the_defaults_are_returned_for_a_fresh_install() {
		$this->acting_as( 'administrator' );

		$data = $this->dispatch( 'GET', '/settings' )->get_data();

		$this->assertSame( '', $data['wplalr_login_redirect'] );
		$this->assertSame( '', $data['wplalr_logout_redirect'] );
		$this->assertSame( array(), $data['rules'] );
		$this->assertFalse( $data['wplalr_enable_logs'] );
		$this->assertSame( 30, $data['wplalr_logs_retention_days'] );
		$this->assertSame( array(), $data['wplalr_logs_notify_roles'] );
		$this->assertSame( '', $data['wplalr_logs_digest'] );
	}

	public function test_the_notification_email_defaults_to_the_site_admin() {
		$this->acting_as( 'administrator' );

		$data = $this->dispatch( 'GET', '/settings' )->get_data();

		$this->assertSame( get_option( 'admin_email' ), $data['wplalr_logs_notification_email'] );
	}

	public function test_stored_values_are_returned() {
		$this->acting_as( 'administrator' );

		update_option( 'wplalr_login_redirect', 'https://example.test/in/' );
		update_option( 'wplalr_enable_logs', 'yes' );
		update_option( 'wplalr_logs_retention_days', 7 );

		$data = $this->dispatch( 'GET', '/settings' )->get_data();

		$this->assertSame( 'https://example.test/in/', $data['wplalr_login_redirect'] );
		$this->assertTrue( $data['wplalr_enable_logs'] );
		$this->assertSame( 7, $data['wplalr_logs_retention_days'] );
	}

	public function test_the_response_is_filterable() {
		$this->acting_as( 'administrator' );

		add_filter(
			'wplalr_rest_settings_response',
			function ( $settings ) {
				$settings['pro_feature'] = true;
				return $settings;
			}
		);

		$this->assertTrue( $this->dispatch( 'GET', '/settings' )->get_data()['pro_feature'] );
	}

	/*
	 * Writing URLs.
	 */

	public function test_saving_a_url_stores_it() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://example.test/in/' ) );

		$this->assertSame( 'https://example.test/in/', get_option( 'wplalr_login_redirect' ) );
	}

	public function test_saving_returns_the_updated_settings() {
		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://example.test/in/' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://example.test/in/', $response->get_data()['wplalr_login_redirect'] );
	}

	public function test_placeholders_survive_a_save() {
		$this->acting_as( 'administrator' );

		$template = '{{website_url}}/hello/{{username}}/';

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => $template ) );

		$this->assertSame( $template, get_option( 'wplalr_login_redirect' ), 'esc_url_raw() would strip the braces.' );
	}

	public function test_placeholders_with_inner_whitespace_survive_a_save() {
		$this->acting_as( 'administrator' );

		$template = '{{ website_url }}/hello/';

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => $template ) );

		$this->assertSame( $template, get_option( 'wplalr_login_redirect' ) );
	}

	public function test_a_url_is_trimmed() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => '  https://example.test/in/  ' ) );

		$this->assertSame( 'https://example.test/in/', get_option( 'wplalr_login_redirect' ) );
	}

	public function test_an_empty_url_is_stored_as_empty() {
		$this->acting_as( 'administrator' );

		update_option( 'wplalr_login_redirect', 'https://example.test/in/' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => '   ' ) );

		$this->assertSame( '', get_option( 'wplalr_login_redirect' ) );
	}

	public function test_a_javascript_url_is_rejected() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'javascript:alert(document.cookie)' ) );

		$this->assertSame( '', get_option( 'wplalr_login_redirect' ) );
	}

	public function test_omitted_fields_are_left_untouched() {
		$this->acting_as( 'administrator' );

		update_option( 'wplalr_logout_redirect', 'https://example.test/out/' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://example.test/in/' ) );

		$this->assertSame( 'https://example.test/out/', get_option( 'wplalr_logout_redirect' ) );
	}

	/*
	 * Writing rules.
	 */

	public function test_a_rule_round_trips() {
		$this->acting_as( 'administrator' );

		$rules = array(
			array(
				'id'         => 'rule-1',
				'enabled'    => true,
				'label'      => 'Editors to the dashboard',
				'conditions' => array(
					array(
						'type'   => 'role',
						'values' => array( 'editor' ),
					),
				),
				'login_url'  => 'https://example.test/editors/',
				'logout_url' => 'https://example.test/bye/',
			),
		);

		$this->dispatch( 'POST', '/settings', array( 'rules' => $rules ) );

		$stored = get_option( RuleEngine::OPTION_RULES );

		$this->assertCount( 1, $stored );
		$this->assertSame( 'rule-1', $stored[0]['id'] );
		$this->assertTrue( $stored[0]['enabled'] );
		$this->assertSame( 'Editors to the dashboard', $stored[0]['label'] );
		$this->assertSame( 'role', $stored[0]['conditions'][0]['type'] );
		$this->assertSame( array( 'editor' ), $stored[0]['conditions'][0]['values'] );
		$this->assertSame( 'https://example.test/editors/', $stored[0]['login_url'] );
	}

	public function test_rule_order_is_preserved() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'      => 'first',
						'enabled' => true,
					),
					array(
						'id'      => 'second',
						'enabled' => true,
					),
				),
			)
		);

		$stored = get_option( RuleEngine::OPTION_RULES );

		$this->assertSame( array( 'first', 'second' ), array_column( $stored, 'id' ) );
	}

	public function test_a_rule_without_an_id_gets_one_generated() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'rules' => array( array( 'label' => 'No id' ) ) ) );

		$stored = get_option( RuleEngine::OPTION_RULES );

		$this->assertNotEmpty( $stored[0]['id'] );
		$this->assertTrue( wp_is_uuid( $stored[0]['id'], 4 ) );
	}

	public function test_enabled_is_always_stored_as_a_boolean() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array( 'id' => 'a' ),
					array(
						'id'      => 'b',
						'enabled' => true,
					),
				),
			)
		);

		$stored = get_option( RuleEngine::OPTION_RULES );

		$this->assertFalse( $stored[0]['enabled'], 'A rule with no enabled flag defaults to off.' );
		$this->assertTrue( $stored[1]['enabled'] );
	}

	public function test_a_rule_label_is_sanitized() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array( 'rules' => array( array( 'label' => '<script>alert(1)</script>Editors' ) ) )
		);

		$this->assertSame( 'Editors', get_option( RuleEngine::OPTION_RULES )[0]['label'] );
	}

	public function test_rule_urls_keep_their_placeholders() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array( 'rules' => array( array( 'login_url' => '{{website_url}}/u/{{user_slug}}/' ) ) )
		);

		$this->assertSame( '{{website_url}}/u/{{user_slug}}/', get_option( RuleEngine::OPTION_RULES )[0]['login_url'] );
	}

	public function test_an_unregistered_condition_type_is_rejected_by_the_schema() {
		$this->acting_as( 'administrator' );

		// The rule schema pins condition.type to an enum, so an unregistered type
		// never reaches sanitize_rules() — the whole request is refused.
		$response = $this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'wc_customer',
								'values' => array( 'yes' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( RuleEngine::OPTION_RULES, false ) );
	}

	public function test_a_condition_with_no_values_is_dropped() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array(),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array(), get_option( RuleEngine::OPTION_RULES )[0]['conditions'] );
	}

	public function test_an_unregistered_role_is_dropped_from_a_condition() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'editor', 'not_a_role' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'editor' ), get_option( RuleEngine::OPTION_RULES )[0]['conditions'][0]['values'] );
	}

	public function test_a_user_condition_keeps_only_real_users() {
		$this->acting_as( 'administrator' );

		$real = self::factory()->user->create();

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'user',
								'values' => array( (string) $real, '999999' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( (string) $real ), get_option( RuleEngine::OPTION_RULES )[0]['conditions'][0]['values'] );
	}

	public function test_duplicate_condition_values_are_collapsed() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'editor', 'editor' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'editor' ), get_option( RuleEngine::OPTION_RULES )[0]['conditions'][0]['values'] );
	}

	public function test_a_malformed_rule_is_rejected_by_the_schema() {
		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'POST', '/settings', array( 'rules' => array( 'garbage', array( 'id' => 'good' ) ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( RuleEngine::OPTION_RULES, false ), 'A bad rule must not partially save the batch.' );
	}

	public function test_saving_an_empty_rule_set_clears_the_option() {
		$this->acting_as( 'administrator' );

		$this->set_rules( array( $this->make_rule() ) );

		$this->dispatch( 'POST', '/settings', array( 'rules' => array() ) );

		$this->assertSame( array(), get_option( RuleEngine::OPTION_RULES ) );
	}

	public function test_a_custom_match_type_can_be_registered_and_sanitized() {
		$this->acting_as( 'administrator' );

		add_filter(
			'wplalr_rule_match_types',
			function ( $types ) {
				$types[] = 'wc_customer';
				return $types;
			}
		);
		add_filter(
			'wplalr_sanitize_condition_values',
			function ( $clean, $type, $values ) {
				return 'wc_customer' === $type ? array_map( 'sanitize_key', $values ) : $clean;
			},
			10,
			3
		);

		// The schema enum is baked in at registration, so the routes must be
		// rebuilt for the widened type to be accepted.
		$this->reboot_server();

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'         => 'r',
						'conditions' => array(
							array(
								'type'   => 'wc_customer',
								'values' => array( 'yes' ),
							),
						),
					),
				),
			)
		);

		$conditions = get_option( RuleEngine::OPTION_RULES )[0]['conditions'];

		$this->assertSame( 'wc_customer', $conditions[0]['type'] );
		$this->assertSame( array( 'yes' ), $conditions[0]['values'] );
	}

	public function test_a_sanitized_rule_is_filterable() {
		$this->acting_as( 'administrator' );

		add_filter(
			'wplalr_rest_sanitize_rule',
			function ( $clean, $raw ) {
				$clean['first_login_only'] = ! empty( $raw['first_login_only'] );
				return $clean;
			},
			10,
			2
		);

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'               => 'r',
						'first_login_only' => true,
					),
				),
			)
		);

		$this->assertTrue( get_option( RuleEngine::OPTION_RULES )[0]['first_login_only'] );
	}

	public function test_unknown_rule_fields_are_dropped_by_default() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array(
				'rules' => array(
					array(
						'id'        => 'r',
						'evil_field' => 'payload',
					),
				),
			)
		);

		$this->assertArrayNotHasKey( 'evil_field', get_option( RuleEngine::OPTION_RULES )[0] );
	}

	/*
	 * Log settings.
	 */

	public function test_enabling_logs_stores_the_yes_no_flag() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_enable_logs' => true ) );
		$this->assertSame( 'yes', get_option( 'wplalr_enable_logs' ) );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_enable_logs' => false ) );
		$this->assertSame( 'no', get_option( 'wplalr_enable_logs' ) );
	}

	public function test_retention_days_are_stored_as_a_positive_integer() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_logs_retention_days' => 45 ) );

		$this->assertSame( 45, (int) get_option( 'wplalr_logs_retention_days' ) );
	}

	public function test_a_negative_retention_is_normalized_to_its_absolute_value() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_logs_retention_days' => -5 ) );

		// absint(), so -5 becomes 5 days rather than "keep forever".
		$this->assertSame( 5, (int) get_option( 'wplalr_logs_retention_days' ) );
	}

	public function test_a_valid_notification_email_is_stored() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_logs_notification_email' => 'ops@example.test' ) );

		$this->assertSame( 'ops@example.test', get_option( 'wplalr_logs_notification_email' ) );
	}

	public function test_an_invalid_notification_email_is_rejected_by_the_schema() {
		$this->acting_as( 'administrator' );

		// The field declares format=email, so a malformed address is refused
		// outright rather than silently falling back to the admin address.
		$response = $this->dispatch( 'POST', '/settings', array( 'wplalr_logs_notification_email' => 'not-an-email' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'wplalr_logs_notification_email', false ) );
	}

	public function test_notify_roles_are_validated_against_the_sites_roles() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array( 'wplalr_logs_notify_roles' => array( 'administrator', 'not_a_role', 'editor' ) )
		);

		$this->assertSame( array( 'administrator', 'editor' ), get_option( 'wplalr_logs_notify_roles' ) );
	}

	public function test_duplicate_notify_roles_are_collapsed() {
		$this->acting_as( 'administrator' );

		$this->dispatch(
			'POST',
			'/settings',
			array( 'wplalr_logs_notify_roles' => array( 'administrator', 'administrator' ) )
		);

		$this->assertSame( array( 'administrator' ), get_option( 'wplalr_logs_notify_roles' ) );
	}

	public function test_a_valid_digest_cadence_is_stored() {
		$this->acting_as( 'administrator' );

		$this->dispatch( 'POST', '/settings', array( 'wplalr_logs_digest' => 'weekly' ) );

		$this->assertSame( 'weekly', get_option( 'wplalr_logs_digest' ) );
	}

	public function test_an_invalid_digest_cadence_is_rejected_by_the_schema() {
		$this->acting_as( 'administrator' );

		$response = $this->dispatch( 'POST', '/settings', array( 'wplalr_logs_digest' => 'hourly' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'wplalr_logs_digest', false ) );
	}
}

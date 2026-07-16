<?php
/**
 * Settings saved over REST take effect on the next login.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

use WP_REST_Request;
use WP_REST_Server;

/**
 * Drives the round trip an admin actually makes: the React settings page POSTs to
 * /wplalr/v1/settings, and the very next login has to honour what was saved.
 *
 * The seam under test is the sanitizer meeting the rule engine — a rule that
 * survives saving but that the engine then reads differently is a bug neither
 * side's unit tests can see.
 *
 * @covers \PluginizeLab\WpLoginLogoutRedirect\REST\SettingsController
 * @covers \PluginizeLab\WpLoginLogoutRedirect\RuleEngine
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Redirection
 */
class SettingsToRedirectFlowTest extends IntegrationTestCase {

	/**
	 * REST server.
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

		wp_set_current_user( $this->make_user( 'administrator' )->ID );
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

	/*
	 * Global options.
	 */

	public function test_a_login_url_saved_over_rest_redirects_the_next_login() {
		$this->save( array( 'wplalr_login_redirect' => home_url( '/members/' ) ) );

		$this->assertSame( home_url( '/members/' ), $this->sign_in( $this->make_user() ) );
	}

	public function test_a_logout_url_saved_over_rest_redirects_the_next_logout() {
		$this->save( array( 'wplalr_logout_redirect' => home_url( '/goodbye/' ) ) );

		$this->assertSame( home_url( '/goodbye/' ), $this->log_out( $this->make_user()->ID ) );
	}

	public function test_clearing_the_login_url_restores_the_dashboard_default() {
		$this->save( array( 'wplalr_login_redirect' => home_url( '/members/' ) ) );
		$this->save( array( 'wplalr_login_redirect' => '' ) );

		$this->assertSame( admin_url(), $this->sign_in( $this->make_user() ) );
	}

	/*
	 * Rules.
	 */

	public function test_a_rule_saved_over_rest_redirects_the_next_login() {
		$this->save(
			array(
				'rules' => array(
					array(
						'id'         => 'rule-editors',
						'enabled'    => true,
						'label'      => 'Editors',
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'editor' ),
							),
						),
						'login_url'  => home_url( '/editor-desk/' ),
						'logout_url' => '',
					),
				),
			)
		);

		$this->assertSame( home_url( '/editor-desk/' ), $this->sign_in( $this->make_user( 'editor' ) ) );
		$this->assertSame( admin_url(), $this->sign_in( $this->make_user( 'subscriber' ) ) );
	}

	public function test_rule_order_saved_over_rest_is_the_order_the_engine_applies() {
		$this->save(
			array(
				'rules' => array(
					array(
						'id'        => 'first',
						'enabled'   => true,
						'login_url' => home_url( '/first/' ),
					),
					array(
						'id'        => 'second',
						'enabled'   => true,
						'login_url' => home_url( '/second/' ),
					),
				),
			)
		);

		$this->assertSame( home_url( '/first/' ), $this->sign_in( $this->make_user() ) );
	}

	public function test_reordering_the_rules_over_rest_changes_the_destination() {
		$first  = array(
			'id'        => 'first',
			'enabled'   => true,
			'login_url' => home_url( '/first/' ),
		);
		$second = array(
			'id'        => 'second',
			'enabled'   => true,
			'login_url' => home_url( '/second/' ),
		);

		$this->save( array( 'rules' => array( $first, $second ) ) );
		$this->assertSame( home_url( '/first/' ), $this->sign_in( $this->make_user() ) );

		$this->save( array( 'rules' => array( $second, $first ) ) );
		$this->assertSame( home_url( '/second/' ), $this->sign_in( $this->make_user() ) );
	}

	public function test_disabling_a_rule_over_rest_stops_it_applying() {
		$rule = array(
			'id'        => 'rule-a',
			'enabled'   => true,
			'login_url' => home_url( '/somewhere/' ),
		);

		$this->save( array( 'rules' => array( $rule ) ) );
		$this->assertSame( home_url( '/somewhere/' ), $this->sign_in( $this->make_user() ) );

		$rule['enabled'] = false;
		$this->save( array( 'rules' => array( $rule ) ) );

		$this->assertSame( admin_url(), $this->sign_in( $this->make_user() ) );
	}

	public function test_deleting_every_rule_over_rest_falls_back_to_the_global_option() {
		$this->save(
			array(
				'wplalr_login_redirect' => home_url( '/members/' ),
				'rules'                 => array(
					array(
						'id'        => 'rule-a',
						'enabled'   => true,
						'login_url' => home_url( '/somewhere/' ),
					),
				),
			)
		);
		$this->assertSame( home_url( '/somewhere/' ), $this->sign_in( $this->make_user() ) );

		$this->save( array( 'rules' => array() ) );

		$this->assertSame( home_url( '/members/' ), $this->sign_in( $this->make_user() ) );
	}

	public function test_a_rule_saved_with_a_placeholder_is_expanded_at_login() {
		$this->save(
			array(
				'rules' => array(
					array(
						'id'        => 'rule-author',
						'enabled'   => true,
						'login_url' => '{{website_url}}/author/{{username}}/',
					),
				),
			)
		);

		$user = $this->make_user( 'subscriber', array( 'user_login' => 'ada' ) );

		$this->assertSame( home_url() . '/author/ada/', $this->sign_in( $user ) );
	}

	public function test_a_capability_rule_survives_the_round_trip() {
		$this->save(
			array(
				'rules' => array(
					array(
						'id'         => 'rule-cap',
						'enabled'    => true,
						'conditions' => array(
							array(
								'type'   => 'capability',
								'values' => array( 'edit_others_posts' ),
							),
						),
						'login_url'  => home_url( '/can-edit/' ),
					),
				),
			)
		);

		$this->assertSame( home_url( '/can-edit/' ), $this->sign_in( $this->make_user( 'editor' ) ) );
		$this->assertSame( admin_url(), $this->sign_in( $this->make_user( 'subscriber' ) ) );
	}

	public function test_a_user_rule_survives_the_round_trip() {
		$targeted = $this->make_user();

		$this->save(
			array(
				'rules' => array(
					array(
						'id'         => 'rule-user',
						'enabled'    => true,
						'conditions' => array(
							array(
								'type'   => 'user',
								'values' => array( (string) $targeted->ID ),
							),
						),
						'login_url'  => home_url( '/vip/' ),
					),
				),
			)
		);

		$this->assertSame( home_url( '/vip/' ), $this->sign_in( $targeted ) );
	}

	/*
	 * Sanitization is what stands between the request and the engine.
	 */

	public function test_a_rule_condition_naming_an_unknown_role_does_not_make_the_rule_universal() {
		$this->markTestIncomplete(
			'SettingsController::sanitize_rules() drops a condition whose role is not registered, '
			. 'and RuleEngine::matches() treats a rule with no conditions as matching everyone. '
			. 'Together they widen a targeted rule into a site-wide one. Either the sanitizer must '
			. 'drop the whole rule, or a rule whose conditions were all stripped must match nobody.'
		);

		$this->save(
			array(
				'rules' => array(
					array(
						'id'         => 'rule-bogus',
						'enabled'    => true,
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'not-a-real-role' ),
							),
						),
						'login_url'  => home_url( '/nowhere/' ),
					),
				),
			)
		);

		$this->assertSame( admin_url(), $this->sign_in( $this->make_user() ) );
	}

	public function test_re_saving_a_rule_whose_role_no_longer_exists_does_not_redirect_everyone() {
		$this->markTestIncomplete(
			'Same defect, reached the way a site actually hits it. A rule targets shop_manager; '
			. 'WooCommerce is deactivated so the role is gone; the admin opens settings and saves '
			. 'without changing anything. The condition is stripped silently and every user on the '
			. 'site — subscribers included — is redirected to the shop dashboard.'
		);

		add_role( 'shop_manager', 'Shop Manager' );

		$this->save(
			array(
				'rules' => array(
					array(
						'id'         => 'rule-shop',
						'enabled'    => true,
						'label'      => 'Shop managers',
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'shop_manager' ),
							),
						),
						'login_url'  => home_url( '/shop-dashboard/' ),
					),
				),
			)
		);

		$this->assertSame( admin_url(), $this->sign_in( $this->make_user( 'subscriber' ) ) );

		// The plugin that registered the role is deactivated.
		remove_role( 'shop_manager' );

		// The settings screen saves back the rules it was handed, unchanged.
		$this->save( array( 'rules' => $this->saved_rules() ) );

		$this->assertSame(
			admin_url(),
			$this->sign_in( $this->make_user( 'subscriber' ) ),
			'A subscriber must never inherit a rule written for shop managers.'
		);
	}

	public function test_an_off_site_rule_url_never_reaches_the_user() {
		$this->save(
			array(
				'rules' => array(
					array(
						'id'        => 'rule-evil',
						'enabled'   => true,
						'login_url' => 'https://evil.example.com/steal',
					),
				),
			)
		);

		$landed = $this->sign_in( $this->make_user() );

		$this->assertStringNotContainsString( 'evil.example.com', $landed );
	}

	public function test_a_rule_saved_without_an_id_is_given_one_that_the_log_can_cite() {
		$this->enable_logs();

		$this->save(
			array(
				'rules' => array(
					array(
						'enabled'   => true,
						'login_url' => home_url( '/somewhere/' ),
					),
				),
			)
		);

		$this->sign_in( $this->make_user() );

		$row = $this->only_log_row();

		$this->assertNotEmpty( $row['rule_id'], 'A generated id must still identify the rule in the audit log.' );
		$this->assertSame( $this->saved_rules()[0]['id'], $row['rule_id'] );
	}

	/*
	 * What the settings screen reads back.
	 */

	public function test_the_settings_endpoint_reads_back_what_was_saved() {
		$this->save(
			array(
				'wplalr_login_redirect'  => home_url( '/members/' ),
				'wplalr_logout_redirect' => home_url( '/goodbye/' ),
			)
		);

		$data = $this->rest( 'GET', '/settings' )->get_data();

		$this->assertSame( home_url( '/members/' ), $data['wplalr_login_redirect'] );
		$this->assertSame( home_url( '/goodbye/' ), $data['wplalr_logout_redirect'] );
	}

	public function test_a_subscriber_cannot_change_where_everyone_gets_redirected() {
		$this->save( array( 'wplalr_login_redirect' => home_url( '/members/' ) ) );

		wp_set_current_user( $this->make_user( 'subscriber' )->ID );

		$response = $this->rest( 'POST', '/settings', array( 'wplalr_login_redirect' => 'https://evil.example.com/' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( home_url( '/members/' ), get_option( 'wplalr_login_redirect' ) );
	}

	/**
	 * POST settings as an admin and assert it succeeded.
	 *
	 * Signing a test user in makes them the current user, so the admin is put back
	 * before each save — an admin visiting the settings screen is the only way
	 * these options ever change.
	 *
	 * @param array $params Settings params.
	 * @return array The response data.
	 */
	protected function save( array $params ) {
		wp_set_current_user( $this->make_user( 'administrator' )->ID );

		$response = $this->rest( 'POST', '/settings', $params );

		$this->assertSame( 200, $response->get_status(), 'The settings should have saved.' );

		return $response->get_data();
	}

	/**
	 * The rules as stored.
	 *
	 * @return array
	 */
	protected function saved_rules() {
		return (array) get_option( 'wplalr_redirect_rules', array() );
	}

	/**
	 * Dispatch against the plugin's REST namespace.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /wplalr/v1.
	 * @param array  $params Params.
	 * @return \WP_REST_Response
	 */
	protected function rest( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/wplalr/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}
}

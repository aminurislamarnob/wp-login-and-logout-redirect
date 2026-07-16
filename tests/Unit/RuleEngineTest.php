<?php
/**
 * Rule engine resolution/matching tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit;

use PluginizeLab\WpLoginLogoutRedirect\RuleEngine;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\RuleEngine
 */
class RuleEngineTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var RuleEngine
	 */
	protected $engine;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->engine = new RuleEngine();
	}

	public function test_get_rules_defaults_to_an_empty_array() {
		$this->assertSame( array(), $this->engine->get_rules() );
	}

	public function test_get_rules_discards_a_non_array_option() {
		update_option( RuleEngine::OPTION_RULES, 'corrupted' );

		$this->assertSame( array(), $this->engine->get_rules() );
	}

	public function test_get_rules_reindexes_a_sparse_option() {
		$this->set_rules(
			array(
				3 => $this->make_rule( array( 'label' => 'first' ) ),
				7 => $this->make_rule( array( 'label' => 'second' ) ),
			)
		);

		$rules = $this->engine->get_rules();

		$this->assertSame( array( 0, 1 ), array_keys( $rules ) );
	}

	public function test_resolve_returns_empty_when_no_rules_exist() {
		$this->assertSame( '', $this->engine->resolve( 'login' ) );
	}

	public function test_a_rule_without_conditions_matches_everyone() {
		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => 'https://example.test/welcome/' ) ),
			)
		);

		$this->assertSame( 'https://example.test/welcome/', $this->engine->resolve( 'login' ) );
	}

	public function test_disabled_rules_are_skipped() {
		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'enabled'   => false,
						'login_url' => 'https://example.test/disabled/',
					)
				),
				$this->make_rule( array( 'login_url' => 'https://example.test/enabled/' ) ),
			)
		);

		$this->assertSame( 'https://example.test/enabled/', $this->engine->resolve( 'login' ) );
	}

	public function test_the_first_matching_rule_wins() {
		$this->set_rules(
			array(
				$this->make_rule( array( 'login_url' => 'https://example.test/one/' ) ),
				$this->make_rule( array( 'login_url' => 'https://example.test/two/' ) ),
			)
		);

		$this->assertSame( 'https://example.test/one/', $this->engine->resolve( 'login' ) );
	}

	public function test_a_matching_rule_without_a_url_for_the_event_does_not_stop_evaluation() {
		// The first rule matches but only carries a logout URL, so a login must
		// keep looking rather than falling through to the global default.
		$this->set_rules(
			array(
				$this->make_rule( array( 'logout_url' => 'https://example.test/bye/' ) ),
				$this->make_rule( array( 'login_url' => 'https://example.test/hi/' ) ),
			)
		);

		$this->assertSame( 'https://example.test/hi/', $this->engine->resolve( 'login' ) );
	}

	public function test_resolve_reads_the_logout_url_for_a_logout() {
		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'login_url'  => 'https://example.test/hi/',
						'logout_url' => 'https://example.test/bye/',
					)
				),
			)
		);

		$this->assertSame( 'https://example.test/bye/', $this->engine->resolve( 'logout' ) );
	}

	public function test_malformed_rules_are_ignored() {
		$this->set_rules(
			array(
				'not-an-array',
				$this->make_rule( array( 'login_url' => 'https://example.test/ok/' ) ),
			)
		);

		$this->assertSame( 'https://example.test/ok/', $this->engine->resolve( 'login' ) );
	}

	public function test_a_conditional_rule_never_matches_a_logged_out_visitor() {
		$this->set_rules(
			array(
				$this->make_rule(
					array(
						'conditions' => array(
							array(
								'type'   => 'role',
								'values' => array( 'subscriber' ),
							),
						),
						'login_url'  => 'https://example.test/subs/',
					)
				),
			)
		);

		$this->assertSame( '', $this->engine->resolve( 'login', null ) );
	}

	public function test_role_condition_matches_the_users_role() {
		$user = $this->user_with_role( 'subscriber' );

		$this->set_rules( array( $this->role_rule( array( 'subscriber' ), 'https://example.test/subs/' ) ) );

		$this->assertSame( 'https://example.test/subs/', $this->engine->resolve( 'login', $user ) );
	}

	public function test_role_condition_rejects_a_different_role() {
		$user = $this->user_with_role( 'editor' );

		$this->set_rules( array( $this->role_rule( array( 'subscriber' ), 'https://example.test/subs/' ) ) );

		$this->assertSame( '', $this->engine->resolve( 'login', $user ) );
	}

	public function test_values_within_one_condition_are_ored() {
		$user = $this->user_with_role( 'editor' );

		$this->set_rules( array( $this->role_rule( array( 'subscriber', 'editor' ), 'https://example.test/staff/' ) ) );

		$this->assertSame( 'https://example.test/staff/', $this->engine->resolve( 'login', $user ) );
	}

	public function test_multiple_conditions_are_anded() {
		$user = $this->user_with_role( 'editor' );

		$rule = $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'role',
						'values' => array( 'editor' ),
					),
					array(
						'type'   => 'user',
						'values' => array( (string) ( $user->ID + 999 ) ),
					),
				),
				'login_url'  => 'https://example.test/nope/',
			)
		);

		$this->set_rules( array( $rule ) );

		// Role passes, user id does not — the AND must fail.
		$this->assertSame( '', $this->engine->resolve( 'login', $user ) );
	}

	public function test_user_condition_matches_by_id() {
		$user = $this->user_with_role( 'subscriber' );

		$rule = $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'user',
						'values' => array( (string) $user->ID ),
					),
				),
				'login_url'  => 'https://example.test/me/',
			)
		);

		$this->set_rules( array( $rule ) );

		$this->assertSame( 'https://example.test/me/', $this->engine->resolve( 'login', $user ) );
	}

	public function test_capability_condition_matches_a_granted_cap() {
		$user = $this->user_with_role( 'editor' );

		$rule = $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'capability',
						'values' => array( 'edit_posts' ),
					),
				),
				'login_url'  => 'https://example.test/editors/',
			)
		);

		$this->set_rules( array( $rule ) );

		$this->assertSame( 'https://example.test/editors/', $this->engine->resolve( 'login', $user ) );
	}

	public function test_capability_condition_rejects_a_missing_cap() {
		$user = $this->user_with_role( 'subscriber' );

		$rule = $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'capability',
						'values' => array( 'manage_options' ),
					),
				),
				'login_url'  => 'https://example.test/admins/',
			)
		);

		$this->set_rules( array( $rule ) );

		$this->assertSame( '', $this->engine->resolve( 'login', $user ) );
	}

	public function test_a_condition_with_no_values_never_passes() {
		$user = $this->user_with_role( 'subscriber' );

		$this->set_rules( array( $this->role_rule( array(), 'https://example.test/subs/' ) ) );

		$this->assertSame( '', $this->engine->resolve( 'login', $user ) );
	}

	public function test_an_unknown_condition_type_falls_through_to_the_match_filter() {
		$user = $this->user_with_role( 'subscriber' );

		$rule = $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'wc_customer',
						'values' => array( 'yes' ),
					),
				),
				'login_url'  => 'https://example.test/pro/',
			)
		);

		$this->set_rules( array( $rule ) );

		$this->assertSame( '', $this->engine->resolve( 'login', $user ), 'Unknown types must not match by default.' );

		$captured = array();
		add_filter(
			'wplalr_match_condition',
			function ( $matched, $type, $values, $filtered_user ) use ( &$captured ) {
				$captured = compact( 'type', 'values', 'filtered_user' );
				return 'wc_customer' === $type;
			},
			10,
			4
		);

		$this->assertSame( 'https://example.test/pro/', $this->engine->resolve( 'login', $user ) );
		$this->assertSame( 'wc_customer', $captured['type'] );
		$this->assertSame( array( 'yes' ), $captured['values'] );
		$this->assertSame( $user->ID, $captured['filtered_user']->ID );
	}

	public function test_resolved_url_is_filterable() {
		$this->set_rules( array( $this->make_rule( array( 'login_url' => 'https://example.test/one/' ) ) ) );

		add_filter(
			'wplalr_resolve_redirect',
			function ( $url, $user, $matched, $event ) {
				return 'https://example.test/overridden/?from=' . rawurlencode( $url ) . '&event=' . $event;
			},
			10,
			4
		);

		$this->assertSame(
			'https://example.test/overridden/?from=' . rawurlencode( 'https://example.test/one/' ) . '&event=login',
			$this->engine->resolve( 'login' )
		);
	}

	public function test_the_resolve_filter_receives_the_matched_rule() {
		$rule = $this->make_rule(
			array(
				'id'        => 'rule-abc',
				'login_url' => 'https://example.test/one/',
			)
		);
		$this->set_rules( array( $rule ) );

		$seen = null;
		add_filter(
			'wplalr_resolve_redirect',
			function ( $url, $user, $matched ) use ( &$seen ) {
				$seen = $matched;
				return $url;
			},
			10,
			3
		);

		$this->engine->resolve( 'login' );

		$this->assertSame( 'rule-abc', $seen['id'] );
	}

	public function test_the_resolve_filter_receives_a_null_rule_when_nothing_matched() {
		$seen = 'unset';
		add_filter(
			'wplalr_resolve_redirect',
			function ( $url, $user, $matched ) use ( &$seen ) {
				$seen = $matched;
				return $url;
			},
			10,
			3
		);

		$this->engine->resolve( 'login' );

		$this->assertNull( $seen );
	}

	public function test_before_and_after_resolve_actions_fire() {
		$this->set_rules( array( $this->make_rule( array( 'login_url' => 'https://example.test/one/' ) ) ) );

		$before = array();
		$after  = array();

		add_action(
			'wplalr_before_resolve',
			function ( $event, $user ) use ( &$before ) {
				$before[] = $event;
			},
			10,
			2
		);
		add_action(
			'wplalr_after_resolve',
			function ( $url, $event, $user, $matched ) use ( &$after ) {
				$after[] = array( $url, $event );
			},
			10,
			4
		);

		$this->engine->resolve( 'login' );

		$this->assertSame( array( 'login' ), $before );
		$this->assertSame( array( array( 'https://example.test/one/', 'login' ) ), $after );
	}

	/**
	 * Create a user with a role and return the WP_User.
	 *
	 * @param string $role Role slug.
	 * @return \WP_User
	 */
	protected function user_with_role( $role ) {
		return get_user_by( 'id', self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Build a rule carrying a single role condition.
	 *
	 * @param array  $roles Role slugs.
	 * @param string $url   Login URL.
	 * @return array
	 */
	protected function role_rule( array $roles, $url ) {
		return $this->make_rule(
			array(
				'conditions' => array(
					array(
						'type'   => 'role',
						'values' => $roles,
					),
				),
				'login_url'  => $url,
			)
		);
	}
}

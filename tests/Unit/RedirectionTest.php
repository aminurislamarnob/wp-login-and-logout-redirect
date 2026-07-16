<?php
/**
 * Login/logout redirect tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit;

use PluginizeLab\WpLoginLogoutRedirect\Placeholders;
use PluginizeLab\WpLoginLogoutRedirect\Redirection;
use PluginizeLab\WpLoginLogoutRedirect\RuleEngine;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * Thrown from the wp_redirect filter to stop before Redirection's exit().
 */
class RedirectCaught extends \Exception {

	/**
	 * The captured location.
	 *
	 * @var string
	 */
	public $location;

	/**
	 * Constructor.
	 *
	 * @param string $location Captured redirect target.
	 */
	public function __construct( $location ) {
		parent::__construct( 'redirect' );
		$this->location = $location;
	}
}

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Redirection
 */
class RedirectionTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var Redirection
	 */
	protected $redirection;

	/**
	 * The REQUEST_URI the suite booted with.
	 *
	 * @var string|null
	 */
	protected $original_request_uri;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;

		// Methods are called directly, so the hooks this constructor adds are
		// incidental — WP_UnitTestCase restores $wp_filter after each test.
		$this->redirection = new Redirection( new RuleEngine(), new Placeholders() );
	}

	/**
	 * Restore the request URI the loop-guard tests overwrite.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		parent::tear_down();
	}

	/*
	 * Login.
	 */

	public function test_login_falls_back_to_the_admin_url_with_nothing_configured() {
		$this->assertSame( admin_url(), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_uses_the_global_option_when_no_rule_matches() {
		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->assertSame( home_url( '/members/' ), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_prefers_a_matching_rule_over_the_global_option() {
		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );
		$this->set_rules( array( $this->make_rule( array( 'login_url' => home_url( '/vip/' ) ) ) ) );

		$this->assertSame( home_url( '/vip/' ), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_resolves_the_rule_for_the_passed_user() {
		$user = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'editor' ) ) );

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
						'login_url'  => home_url( '/editors/' ),
					)
				),
			)
		);

		$this->assertSame( home_url( '/editors/' ), $this->redirection->login_redirect( '', '', $user ) );
	}

	public function test_login_expands_placeholders() {
		$user = get_user_by( 'id', self::factory()->user->create( array( 'user_login' => 'zoe' ) ) );
		update_option( 'wplalr_login_redirect', '{{website_url}}/hi/{{username}}/' );

		$this->assertSame( home_url() . '/hi/zoe/', $this->redirection->login_redirect( '', '', $user ) );
	}

	public function test_login_falls_back_to_the_current_user_when_none_is_passed() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

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
						'login_url'  => home_url( '/editors/' ),
					)
				),
			)
		);

		// WP_Error is what core passes on a failed login; it must not be treated
		// as a user, and the current user should be resolved instead.
		$this->assertSame( home_url( '/editors/' ), $this->redirection->login_redirect( '', '', new \WP_Error( 'nope' ) ) );
	}

	public function test_login_rejects_an_external_host_and_uses_the_hook_fallback() {
		update_option( 'wplalr_login_redirect', 'https://evil.test/steal/' );

		$fallback = home_url( '/safe/' );

		$this->assertSame( $fallback, $this->redirection->login_redirect( $fallback, '', null ) );
	}

	public function test_login_rejects_an_external_host_and_uses_the_admin_url_without_a_fallback() {
		update_option( 'wplalr_login_redirect', 'https://evil.test/steal/' );

		$this->assertSame( admin_url(), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_never_loops_back_to_the_login_screen() {
		update_option( 'wplalr_login_redirect', wp_login_url() );

		$this->assertSame( admin_url(), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_never_redirects_to_the_url_being_requested() {
		$_SERVER['REQUEST_URI'] = '/members/';
		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->assertSame( admin_url(), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_login_ignores_a_trailing_slash_when_detecting_a_loop() {
		$_SERVER['REQUEST_URI'] = '/members';
		update_option( 'wplalr_login_redirect', home_url( '/members/' ) );

		$this->assertSame( admin_url(), $this->redirection->login_redirect( '', '', null ) );
	}

	public function test_woocommerce_login_redirect_resolves_the_same_way() {
		update_option( 'wplalr_login_redirect', home_url( '/shop-account/' ) );

		$this->assertSame( home_url( '/shop-account/' ), $this->redirection->woocommerce_login_redirect( '', null ) );
	}

	/*
	 * Logout.
	 */

	public function test_logout_falls_back_to_the_home_url() {
		$this->assertSame( home_url(), $this->capture_logout() );
	}

	public function test_logout_uses_the_global_option() {
		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );

		$this->assertSame( home_url( '/goodbye/' ), $this->capture_logout() );
	}

	public function test_logout_prefers_a_matching_rule() {
		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );
		$this->set_rules( array( $this->make_rule( array( 'logout_url' => home_url( '/see-ya/' ) ) ) ) );

		$this->assertSame( home_url( '/see-ya/' ), $this->capture_logout() );
	}

	public function test_logout_expands_placeholders_for_the_user_that_logged_out() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'zoe' ) );
		update_option( 'wplalr_logout_redirect', '{{website_url}}/bye/{{username}}/' );

		$this->assertSame( home_url() . '/bye/zoe/', $this->capture_logout( $user_id ) );
	}

	public function test_logout_allows_a_configured_external_host_by_default() {
		update_option( 'wplalr_logout_redirect', 'https://partner.test/farewell/' );

		$this->assertSame( 'https://partner.test/farewell/', $this->capture_logout() );
	}

	public function test_logout_external_host_can_be_blocked_by_filter() {
		update_option( 'wplalr_logout_redirect', 'https://partner.test/farewell/' );

		add_filter( 'wplalr_allow_external_redirect', '__return_false' );

		$this->assertSame( home_url(), $this->capture_logout() );
	}

	public function test_logout_never_redirects_to_the_url_being_requested() {
		$_SERVER['REQUEST_URI'] = '/goodbye/';
		update_option( 'wplalr_logout_redirect', home_url( '/goodbye/' ) );

		$this->assertSame( home_url(), $this->capture_logout() );
	}

	/**
	 * Run the logout redirect and return the URL it tried to send the user to.
	 *
	 * Redirection::redirect_after_logout() ends in exit(), so the wp_redirect
	 * filter is used to grab the location and unwind before that.
	 *
	 * @param int $user_id User id to log out, or 0 for the current user.
	 * @return string
	 */
	protected function capture_logout( $user_id = 0 ) {
		$catch = function ( $location ) {
			throw new RedirectCaught( $location );
		};

		add_filter( 'wp_redirect', $catch, 1 );

		try {
			$this->redirection->redirect_after_logout( $user_id );
		} catch ( RedirectCaught $caught ) {
			return $caught->location;
		} finally {
			remove_filter( 'wp_redirect', $catch, 1 );
		}

		$this->fail( 'redirect_after_logout() did not attempt a redirect.' );
	}
}

<?php
/**
 * Placeholder expansion tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit;

use PluginizeLab\WpLoginLogoutRedirect\Placeholders;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Placeholders
 */
class PlaceholdersTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var Placeholders
	 */
	protected $placeholders;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->placeholders = new Placeholders();
	}

	public function test_map_always_contains_the_website_url() {
		$map = $this->placeholders->get_map();

		$this->assertSame( home_url(), $map['{{website_url}}'] );
	}

	public function test_map_omits_user_tokens_without_a_user() {
		$map = $this->placeholders->get_map();

		$this->assertArrayNotHasKey( '{{username}}', $map );
		$this->assertArrayNotHasKey( '{{user_slug}}', $map );
	}

	public function test_map_includes_user_tokens_for_a_real_user() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'    => 'jane_doe',
				'user_nicename' => 'jane-doe',
			)
		);

		$map = $this->placeholders->get_map( get_user_by( 'id', $user_id ) );

		$this->assertSame( 'jane_doe', $map['{{username}}'] );
		$this->assertSame( 'jane-doe', $map['{{user_slug}}'] );
	}

	public function test_map_omits_user_tokens_for_a_non_existent_user() {
		$map = $this->placeholders->get_map( new \WP_User( 0 ) );

		$this->assertArrayNotHasKey( '{{username}}', $map );
	}

	public function test_map_is_filterable() {
		add_filter(
			'wplalr_placeholders',
			function ( $map ) {
				$map['{{current_page}}'] = 'https://example.test/now/';
				return $map;
			}
		);

		$map = $this->placeholders->get_map();

		$this->assertSame( 'https://example.test/now/', $map['{{current_page}}'] );
	}

	public function test_replace_expands_every_token() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'    => 'jane_doe',
				'user_nicename' => 'jane-doe',
			)
		);
		$user    = get_user_by( 'id', $user_id );

		$actual = $this->placeholders->replace( '{{website_url}}/author/{{user_slug}}/?u={{username}}', $user );

		$this->assertSame( home_url() . '/author/jane-doe/?u=jane_doe', $actual );
	}

	public function test_replace_leaves_user_tokens_untouched_without_a_user() {
		$actual = $this->placeholders->replace( '{{website_url}}/hi/{{username}}/' );

		// Unknown tokens are left verbatim rather than collapsing to an empty segment.
		$this->assertSame( home_url() . '/hi/{{username}}/', $actual );
	}

	public function test_replace_short_circuits_a_url_without_tokens() {
		$url = 'https://example.test/dashboard/';

		$this->assertSame( $url, $this->placeholders->replace( $url ) );
	}

	public function test_replace_passes_empty_values_through() {
		$this->assertSame( '', $this->placeholders->replace( '' ) );
	}

	public function test_replace_uses_filtered_tokens() {
		add_filter(
			'wplalr_placeholders',
			function ( $map ) {
				$map['{{previous_page}}'] = 'https://example.test/back/';
				return $map;
			}
		);

		$this->assertSame(
			'https://example.test/back/',
			$this->placeholders->replace( '{{previous_page}}' )
		);
	}
}

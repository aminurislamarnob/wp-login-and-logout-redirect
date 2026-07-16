<?php
/**
 * User-agent parser tests.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Unit\Logs;

use PluginizeLab\WpLoginLogoutRedirect\Logs\UserAgent;
use PluginizeLab\WpLoginLogoutRedirect\Tests\TestCase;

/**
 * @covers \PluginizeLab\WpLoginLogoutRedirect\Logs\UserAgent
 */
class UserAgentTest extends TestCase {

	/**
	 * Real-world user-agent strings and what they should parse to.
	 *
	 * @return array<string, array{0:string, 1:string, 2:string}>
	 */
	public function ua_provider() {
		return array(
			'Chrome on Windows'   => array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Chrome',
				'Windows',
			),
			'Chrome on macOS'     => array(
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Chrome',
				'macOS',
			),
			'Safari on macOS'     => array(
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
				'Safari',
				'macOS',
			),
			'Safari on iPhone'    => array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
				'Safari',
				'iOS',
			),
			'Firefox on Linux'    => array(
				'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
				'Firefox',
				'Linux',
			),
			'Firefox on iOS'      => array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/121.0 Mobile/15E148 Safari/605.1.15',
				'Firefox',
				'iOS',
			),
			'Chrome on Android'   => array(
				'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
				'Chrome',
				'Android',
			),
			'Chrome on iOS'       => array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1',
				'Chrome',
				'iOS',
			),
			'Edge on Windows'     => array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
				'Edge',
				'Windows',
			),
			'Opera on Windows'    => array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
				'Opera',
				'Windows',
			),
			'Samsung Internet'    => array(
				'Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36',
				'Samsung Internet',
				'Android',
			),
			'Internet Explorer'   => array(
				'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
				'Internet Explorer',
				'Windows',
			),
			'Chrome OS'           => array(
				'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Chrome',
				'Chrome OS',
			),
		);
	}

	/**
	 * @dataProvider ua_provider
	 *
	 * @param string $ua       User-agent string.
	 * @param string $browser  Expected browser.
	 * @param string $os       Expected OS.
	 */
	public function test_parses_real_user_agents( $ua, $browser, $os ) {
		$parsed = UserAgent::parse( $ua );

		$this->assertSame( $browser, $parsed['browser'] );
		$this->assertSame( $os, $parsed['device_os'] );
	}

	public function test_unrecognised_agent_degrades_to_unknown() {
		$parsed = UserAgent::parse( 'curl/8.4.0' );

		$this->assertSame( 'Unknown', $parsed['browser'] );
		$this->assertSame( 'Unknown', $parsed['device_os'] );
	}

	public function test_empty_agent_degrades_to_unknown() {
		$parsed = UserAgent::parse( '' );

		$this->assertSame( 'Unknown', $parsed['browser'] );
		$this->assertSame( 'Unknown', $parsed['device_os'] );
	}

	public function test_non_string_input_is_cast_safely() {
		$parsed = UserAgent::parse( null );

		$this->assertSame( 'Unknown', $parsed['browser'] );
	}

	public function test_parse_always_returns_both_keys() {
		$this->assertSame(
			array( 'browser', 'device_os' ),
			array_keys( UserAgent::parse( 'anything' ) )
		);
	}
}

<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny, dependency-free user-agent parser.
 *
 * Extracts a coarse browser name and operating system from a UA string. This is
 * intentionally compact (a regex map) rather than a full UA database — it covers
 * the common cases for an audit-log display and degrades to "Unknown".
 */
class UserAgent {

	/**
	 * Parse a user-agent string into browser + OS labels.
	 *
	 * @param string $ua Raw user-agent string.
	 * @return array{browser:string, device_os:string}
	 */
	public static function parse( $ua ) {
		$ua = (string) $ua;

		return array(
			'browser'   => self::detect_browser( $ua ),
			'device_os' => self::detect_os( $ua ),
		);
	}

	/**
	 * Detect the browser name. Order matters (e.g. Edge/Opera before Chrome,
	 * Chrome before Safari) because their UA strings overlap.
	 *
	 * @param string $ua User-agent string.
	 * @return string
	 */
	protected static function detect_browser( $ua ) {
		$map = array(
			'Edge'              => '/Edg(e|A|iOS)?\//i',
			'Opera'            => '/OPR\/|Opera/i',
			'Samsung Internet' => '/SamsungBrowser/i',
			'Firefox'          => '/Firefox\/|FxiOS/i',
			'Chrome'           => '/Chrome\/|CriOS/i',
			'Safari'           => '/Safari\//i',
			'Internet Explorer' => '/MSIE |Trident\//i',
		);

		foreach ( $map as $name => $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}

		return __( 'Unknown', 'wp-login-logout-redirect' );
	}

	/**
	 * Detect the operating system.
	 *
	 * @param string $ua User-agent string.
	 * @return string
	 */
	protected static function detect_os( $ua ) {
		$map = array(
			'Windows'  => '/Windows NT/i',
			'Android'  => '/Android/i',
			'iOS'      => '/iPhone|iPad|iPod/i',
			'macOS'    => '/Macintosh|Mac OS X/i',
			'Linux'    => '/Linux/i',
			'Chrome OS' => '/CrOS/i',
		);

		foreach ( $map as $name => $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}

		return __( 'Unknown', 'wp-login-logout-redirect' );
	}
}

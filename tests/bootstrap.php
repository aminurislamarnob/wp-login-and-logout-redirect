<?php
/**
 * PHPUnit bootstrap: boots the WordPress core test suite with this plugin loaded.
 *
 * Run bin/install-wp-tests.sh once before the first run. Point WP_TESTS_DIR at a
 * custom test-suite location if it does not live in the system temp dir.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

$wplalr_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wplalr_tests_dir ) {
	$wplalr_tmp       = getenv( 'TMPDIR' ) ? rtrim( getenv( 'TMPDIR' ), '/' ) : '/tmp';
	$wplalr_tests_dir = $wplalr_tmp . '/wordpress-tests-lib';
}

if ( ! file_exists( $wplalr_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test suite at ' . $wplalr_tests_dir . PHP_EOL // phpcs:ignore
		. 'Run bin/install-wp-tests.sh wplalr_tests root root 127.0.0.1 latest' . PHP_EOL;
	exit( 1 );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once $wplalr_tests_dir . '/includes/functions.php';

/**
 * Load the plugin into the test WordPress install.
 */
function wplalr_manually_load_plugin() {
	require __DIR__ . '/../wp-login-logout-redirect.php';
}
tests_add_filter( 'muplugins_loaded', 'wplalr_manually_load_plugin' );

require $wplalr_tests_dir . '/includes/bootstrap.php';

/*
 * Create the audit-log table once, up front.
 *
 * WP_UnitTestCase wraps each test in a transaction, but DDL implicitly commits in
 * MySQL — so the table must exist before the first transaction opens rather than
 * being created per-test.
 */
( new PluginizeLab\WpLoginLogoutRedirect\Logs\Installer() )->install();

// Base classes: PHPUnit only autoloads files matching the *Test.php suffix.
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/REST/RestTestCase.php';

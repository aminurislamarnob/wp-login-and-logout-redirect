const { defineConfig, devices } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Playwright config for the e2e suite.
 *
 * Targets the local Herd site rather than a container, so there is no webServer
 * to boot and no parallelism: every spec mutates the same site-wide plugin
 * options, and two workers would silently overwrite each other's setup. One
 * worker keeps the suite honest at the cost of wall-clock time.
 */
module.exports = defineConfig( {
	testDir: './e2e/specs',
	globalSetup: require.resolve( './e2e/global-setup.js' ),
	globalTeardown: require.resolve( './e2e/global-teardown.js' ),

	// One site, shared mutable state. Do not raise these.
	workers: 1,
	fullyParallel: false,

	forbidOnly: !! process.env.CI,
	retries: 0,

	// This is a real WooCommerce dev site, not a bare test container: a cold
	// wp-admin load can take tens of seconds, and that is the environment, not
	// the plugin. Generous navigation headroom keeps failures meaningful.
	timeout: 90000,
	expect: { timeout: 10000 },
	reporter: process.env.CI ? 'list' : [ [ 'list' ], [ 'html', { open: 'never' } ] ],

	use: {
		baseURL: process.env.WP_BASE_URL || 'http://woocommerce.test',
		navigationTimeout: 60000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},

	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: path.join( __dirname, 'e2e/.state/admin.json' ),
			},
		},
	],
} );

/**
 * Shared fixtures.
 *
 * The plugin's options are site-wide, so each test starts by wiping them back to
 * "fresh install" through wp-cli. That is what makes the specs order-independent
 * even though they all share one WordPress.
 */
const base = require( '@playwright/test' );
const {
	wp,
	getOption,
	setOption,
	deleteOption,
	evalPhp,
} = require( './utils/wp-cli' );
const { USERS, PASSWORD, PLUGIN_OPTIONS, PAGES } = require( './utils/constants' );

/**
 * Reset the plugin to a fresh-install state.
 *
 * @return {void}
 */
function resetPlugin() {
	for ( const name of PLUGIN_OPTIONS ) {
		deleteOption( name );
	}

	truncateLogs();

	// Sessions accumulate: redirect journeys sign users in for real and the
	// sessions screen seeds more, so without this every session count drifts
	// upward across the run. The test admin is deliberately spared — their
	// session backs the shared storageState every admin-page spec relies on.
	const doomed = Object.values( USERS )
		.filter( ( user ) => user.role !== 'administrator' )
		.map( ( user ) => user.login );

	evalPhp(
		`foreach ( json_decode( '${ JSON.stringify( doomed ) }' ) as $login ) {` +
			`$u = get_user_by( 'login', $login );` +
			`if ( $u ) { WP_Session_Tokens::get_instance( $u->ID )->destroy_all(); }` +
			`}`
	);
}

/**
 * Empty the audit-log table.
 *
 * @return {void}
 */
function truncateLogs() {
	evalPhp(
		'global $wpdb; $t = PluginizeLab\\WpLoginLogoutRedirect\\Logs\\Installer::table_name(); $wpdb->query( "DELETE FROM {$t}" );'
	);
}

/**
 * Read every audit-log row, oldest first.
 *
 * @return {Array} Rows.
 */
function logRows() {
	const json = evalPhp(
		'global $wpdb; $t = PluginizeLab\\WpLoginLogoutRedirect\\Logs\\Installer::table_name(); ' +
			'echo wp_json_encode( $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id ASC", ARRAY_A ) );'
	);

	return json ? JSON.parse( json ) : [];
}

/**
 * Insert an audit-log row.
 *
 * @param {Object} row      Column values.
 * @param {number} daysAgo  Backdate the row this many days.
 * @return {void}
 */
function seedLogRow( row, daysAgo = 0 ) {
	const data = {
		event: 'login',
		status: 'success',
		...row,
	};

	if ( daysAgo > 0 ) {
		data.created_at = new Date( Date.now() - daysAgo * 86400000 )
			.toISOString()
			.slice( 0, 19 )
			.replace( 'T', ' ' );
	}

	evalPhp(
		`( new PluginizeLab\\WpLoginLogoutRedirect\\Logs\\LogRepository() )->insert( json_decode( '${ JSON.stringify(
			data
		) }', true ) );`
	);
}

/**
 * Give a user a live session, as if they had signed in on another device.
 *
 * @param {string} login    Username.
 * @param {number} howMany  Number of sessions to mint.
 * @return {void}
 */
function seedSessions( login, howMany = 1 ) {
	evalPhp(
		`$u = get_user_by( 'login', '${ login }' ); ` +
			`$m = WP_Session_Tokens::get_instance( $u->ID ); ` +
			`for ( $i = 0; $i < ${ howMany }; $i++ ) { $m->create( time() + DAY_IN_SECONDS ); }`
	);
}

/**
 * Count a user's live sessions.
 *
 * @param {string} login Username.
 * @return {number} Session count.
 */
function sessionCount( login ) {
	const out = evalPhp(
		`$u = get_user_by( 'login', '${ login }' ); ` +
			`$s = get_user_meta( $u->ID, 'session_tokens', true ); ` +
			`echo is_array( $s ) ? count( $s ) : 0;`
	);

	return parseInt( out, 10 ) || 0;
}

/**
 * The numeric id of a throwaway user.
 *
 * @param {string} login Username.
 * @return {number} User id.
 */
function userId( login ) {
	return parseInt( wp( [ 'user', 'get', login, '--field=ID' ] ), 10 );
}

/**
 * Match a site URL regardless of scheme.
 *
 * WordPress's home_url() here is http://woocommerce.test, so that is what the
 * plugin stores and emits — but Herd answers http with a 301 to https, so the
 * browser always finishes on https. Asserting the literal option value would
 * fail on a hosting detail that has nothing to do with the plugin.
 *
 * @param {string} path Path beginning with a slash.
 * @return {RegExp} Matcher for toHaveURL.
 */
function sitePath( path ) {
	const escaped = path.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

	return new RegExp( `^https?://woocommerce\\.test${ escaped }$` );
}

/**
 * Build a rule with sane defaults.
 *
 * @param {Object} overrides Fields to override.
 * @return {Object} A rule.
 */
function makeRule( overrides = {} ) {
	return {
		id: `wplalr_e2e_rule_${ Math.random().toString( 36 ).slice( 2, 8 ) }`,
		enabled: true,
		label: 'E2E rule',
		conditions: [],
		login_url: '',
		logout_url: '',
		...overrides,
	};
}

const test = base.test.extend( {
	/**
	 * Wipe plugin state before each test.
	 */
	freshPlugin: [
		async ( {}, use ) => {
			resetPlugin();
			await use();
		},
		{ auto: true },
	],

	/**
	 * The plugin's own UI, scoped away from the WordPress admin chrome.
	 *
	 * The sidebar menu repeats the plugin's page names, so an unscoped
	 * getByRole('link', { name: 'Audit Logs' }) is ambiguous. Specs assert
	 * against this, not the whole page.
	 */
	app: async ( { page }, use ) => {
		await use(
			page.locator(
				'#wplalr-settings, #wplalr-audit-logs, #wplalr-sessions'
			)
		);
	},

	/**
	 * Admin-page helpers.
	 */
	admin: async ( { page }, use ) => {
		/**
		 * Wait for the React app to finish its initial settings fetch.
		 *
		 * The shell renders immediately but the tabs are skeletons until the
		 * GET /settings request lands, so asserting on the header alone would
		 * race the real content.
		 */
		const waitForApp = async () => {
			await page
				.getByRole( 'heading', { name: 'WP Login and Logout Redirect' } )
				.waitFor();
			await page
				.locator( '.wplalr-skeleton-tab' )
				.first()
				.waitFor( { state: 'detached' } )
				.catch( () => {} );
		};

		await use( {
			async visitSettings( hash = '' ) {
				await page.goto( `/wp-admin/${ PAGES.settings }${ hash }` );
				await waitForApp();
			},
			async visitLogs() {
				await page.goto( `/wp-admin/${ PAGES.logs }` );
				await waitForApp();
			},
			async visitSessions() {
				await page.goto( `/wp-admin/${ PAGES.sessions }` );
				await waitForApp();
			},
			waitForApp,
		} );
	},

	/**
	 * A clean browser context signed in as one of the throwaway users.
	 *
	 * Deliberately not the shared admin storageState: the redirect journeys need
	 * to watch a real sign-in happen.
	 */
	signInAs: async ( { browser }, use ) => {
		const contexts = [];

		await use( async ( key ) => {
			const context = await browser.newContext( { storageState: undefined } );
			contexts.push( context );

			const page = await context.newPage();
			await page.goto( '/wp-login.php' );
			await page.fill( '#user_login', USERS[ key ].login );
			await page.fill( '#user_pass', PASSWORD );
			await page.click( '#wp-submit' );

			return page;
		} );

		for ( const context of contexts ) {
			await context.close();
		}
	},
} );

module.exports = {
	test,
	expect: base.expect,
	USERS,
	PASSWORD,
	PAGES,
	wp,
	getOption,
	setOption,
	deleteOption,
	evalPhp,
	logRows,
	seedLogRow,
	seedSessions,
	sessionCount,
	userId,
	makeRule,
	truncateLogs,
	sitePath,
};

const {
	test,
	expect,
	sitePath,
	setOption,
	makeRule,
	logRows,
	USERS,
} = require( '../fixtures' );

const BASE = 'http://woocommerce.test';

/**
 * Log a signed-in page out through the real admin-bar flow.
 *
 * @param {import('@playwright/test').Page} page A signed-in page.
 * @return {Promise<void>}
 */
async function logOut( page ) {
	// Not via the admin bar: WooCommerce bounces subscribers out of wp-admin, so
	// there is no admin bar to read a nonce from. Hitting the logout action
	// without a nonce makes wp-login.php render its own confirmation link with a
	// valid nonce — core's supported path for exactly this situation.
	await page.goto( '/wp-login.php?action=logout' );
	await page.getByRole( 'link', { name: 'log out' } ).click();
}

test.describe( 'logout redirect', () => {
	test( 'the global option sends the user to the configured page', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_logout_redirect', `${ BASE }/goodbye/` );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		await expect( page ).toHaveURL( sitePath( '/goodbye/' ) );
	} );

	test( 'with nothing configured the user lands on the home page', async ( {
		signInAs,
	} ) => {
		const page = await signInAs( 'subscriber' );
		await logOut( page );

		await expect( page ).toHaveURL( sitePath( '/' ) );
	} );

	test( 'a role rule beats the global option', async ( { signInAs } ) => {
		setOption( 'wplalr_logout_redirect', `${ BASE }/goodbye/` );
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				conditions: [ { type: 'role', values: [ 'editor' ] } ],
				logout_url: `${ BASE }/bye-editor/`,
			} ),
		] );

		const page = await signInAs( 'editor' );
		await logOut( page );

		await expect( page ).toHaveURL( sitePath( '/bye-editor/' ) );
	} );

	test( 'a rule with only a login URL does not hijack the logout', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { login_url: `${ BASE }/login-only/` } ),
		] );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		await expect( page ).toHaveURL( sitePath( '/' ) );
	} );

	test( 'placeholders are expanded for the user logging out', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				logout_url: '{{website_url}}/farewell/{{username}}/',
			} ),
		] );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		await expect( page ).toHaveURL(
			sitePath( `/farewell/${ USERS.subscriber.login }/` )
		);
	} );

	test( 'the user really is signed out afterwards', async ( { signInAs } ) => {
		setOption( 'wplalr_logout_redirect', `${ BASE }/goodbye/` );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		// The redirect must not come at the cost of actually ending the session.
		await page.goto( '/wp-admin/' );
		await expect( page ).toHaveURL( /wp-login\.php/ );
	} );
} );

test.describe( 'logout audit log', () => {
	test( 'a logout is logged with the destination it actually used', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );
		setOption( 'wplalr_logout_redirect', `${ BASE }/goodbye/` );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		const rows = logRows().filter( ( r ) => r.event === 'logout' );

		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].username ).toBe( USERS.subscriber.login );
		// Regression: NULL here whenever the option, not a rule, chose the URL.
		expect( rows[ 0 ].redirect_url ).toBe( `${ BASE }/goodbye/` );
	} );

	test( 'a full session leaves a login and a logout row', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );

		const page = await signInAs( 'subscriber' );
		await logOut( page );

		const events = logRows().map( ( r ) => r.event );

		expect( events ).toEqual( [ 'login', 'logout' ] );
	} );
} );

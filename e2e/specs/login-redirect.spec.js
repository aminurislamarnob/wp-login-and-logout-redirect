const {
	test,
	expect,
	sitePath,
	setOption,
	makeRule,
	userId,
	logRows,
	USERS,
} = require( '../fixtures' );

const BASE = 'http://woocommerce.test';

/**
 * The journey a real user takes: submit wp-login.php and see where you land.
 *
 * These are the only tests in the project that exercise the redirect through an
 * actual browser navigation. The PHPUnit suite asserts what the filter returns;
 * this asserts that WordPress honours it.
 */
test.describe( 'login redirect', () => {
	test( 'the global option sends the user to the configured page', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );

		const page = await signInAs( 'subscriber' );

		await expect( page ).toHaveURL( sitePath( '/members/' ) );
	} );

	test( 'with nothing configured the user lands on the dashboard', async ( {
		signInAs,
	} ) => {
		// An editor, not a subscriber: WooCommerce bounces users without
		// edit_posts out of wp-admin, which would mask the plugin's fallback.
		const page = await signInAs( 'editor' );

		await expect( page ).toHaveURL( /\/wp-admin\/?$/ );
	} );

	test( 'a role rule beats the global option', async ( { signInAs } ) => {
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				label: 'Editors',
				conditions: [ { type: 'role', values: [ 'editor' ] } ],
				login_url: `${ BASE }/editor-desk/`,
			} ),
		] );

		const page = await signInAs( 'editor' );

		await expect( page ).toHaveURL( sitePath( '/editor-desk/' ) );
	} );

	test( 'a user the rule does not match falls back to the option', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				conditions: [ { type: 'role', values: [ 'editor' ] } ],
				login_url: `${ BASE }/editor-desk/`,
			} ),
		] );

		const page = await signInAs( 'subscriber' );

		await expect( page ).toHaveURL( sitePath( '/members/' ) );
	} );

	test( 'a capability rule matches the user that has it', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				conditions: [
					{ type: 'capability', values: [ 'edit_others_posts' ] },
				],
				login_url: `${ BASE }/can-edit/`,
			} ),
		] );

		const page = await signInAs( 'editor' );

		await expect( page ).toHaveURL( sitePath( '/can-edit/' ) );
	} );

	test( 'a specific-user rule targets only that account', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				conditions: [
					{
						type: 'user',
						values: [ String( userId( USERS.subscriber.login ) ) ],
					},
				],
				login_url: `${ BASE }/vip/`,
			} ),
		] );

		const targeted = await signInAs( 'subscriber' );
		await expect( targeted ).toHaveURL( sitePath( '/vip/' ) );

		const other = await signInAs( 'editor' );
		await expect( other ).toHaveURL( /\/wp-admin\/?$/ );
	} );

	test( 'the first matching rule wins', async ( { signInAs } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { label: 'First', login_url: `${ BASE }/first/` } ),
			makeRule( { label: 'Second', login_url: `${ BASE }/second/` } ),
		] );

		const page = await signInAs( 'subscriber' );

		await expect( page ).toHaveURL( sitePath( '/first/' ) );
	} );

	test( 'a disabled rule is skipped', async ( { signInAs } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				enabled: false,
				login_url: `${ BASE }/disabled/`,
			} ),
			makeRule( { login_url: `${ BASE }/live/` } ),
		] );

		const page = await signInAs( 'subscriber' );

		await expect( page ).toHaveURL( sitePath( '/live/' ) );
	} );

	test( 'placeholders are expanded for the user signing in', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { login_url: '{{website_url}}/author/{{username}}/' } ),
		] );

		const page = await signInAs( 'subscriber' );

		await expect( page ).toHaveURL( sitePath( `/author/${ USERS.subscriber.login }/` ) );
	} );

	test( 'an off-site redirect is refused', async ( { signInAs } ) => {
		setOption( 'wplalr_login_redirect', 'https://evil.example.com/steal' );

		const page = await signInAs( 'subscriber' );

		// wp_validate_redirect drops the external host rather than following it.
		expect( page.url() ).not.toContain( 'evil.example.com' );
	} );

	test( 'signing in records the last login time on the users screen', async ( {
		page,
		signInAs,
	} ) => {
		await signInAs( 'subscriber' );

		await page.goto( '/wp-admin/users.php' );

		const row = page.locator( 'tr', {
			hasText: USERS.subscriber.login,
		} );

		await expect( row.locator( '.wplalr_last_login' ) ).not.toHaveText(
			'-'
		);
	} );

	test( 'another plugin column is not blanked by ours', async ( {
		page,
		signInAs,
	} ) => {
		await signInAs( 'subscriber' );

		await page.goto( '/wp-admin/users.php' );

		// The Posts column is core's own; a filter that returns its own buffer
		// for every column would empty this.
		const row = page.locator( 'tr', { hasText: USERS.editor.login } );

		await expect( row.locator( '.posts' ) ).toBeVisible();
	} );
} );

test.describe( 'login audit log', () => {
	test( 'a login is logged with the destination it actually used', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );

		await signInAs( 'subscriber' );

		const rows = logRows().filter( ( r ) => r.event === 'login' );

		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].username ).toBe( USERS.subscriber.login );
		// Regression: the logger used to record NULL whenever the destination
		// came from the global option rather than a rule.
		expect( rows[ 0 ].redirect_url ).toBe( `${ BASE }/members/` );
	} );

	test( 'a rule-driven login logs the expanded URL and the rule id', async ( {
		signInAs,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				id: 'wplalr_e2e_rule_authors',
				login_url: '{{website_url}}/author/{{username}}/',
			} ),
		] );

		await signInAs( 'subscriber' );

		const [ row ] = logRows().filter( ( r ) => r.event === 'login' );

		expect( row.rule_id ).toBe( 'wplalr_e2e_rule_authors' );
		// Regression: this used to log the raw, unexpanded placeholder text.
		// Scheme-agnostic: {{website_url}} expands via home_url(), which follows
		// the request scheme, and Herd upgrades every request to https.
		expect( row.redirect_url ).toMatch(
			sitePath( `/author/${ USERS.subscriber.login }/` )
		);
	} );

	test( 'a failed login is logged', async ( { browser } ) => {
		setOption( 'wplalr_enable_logs', 'yes' );

		const context = await browser.newContext( { storageState: undefined } );
		const page = await context.newPage();

		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', USERS.subscriber.login );
		await page.fill( '#user_pass', 'definitely-the-wrong-password' );
		await page.click( '#wp-submit' );

		await expect( page.locator( '#login_error' ) ).toBeVisible();

		const rows = logRows().filter( ( r ) => r.event === 'failed' );

		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].username ).toBe( USERS.subscriber.login );

		await context.close();
	} );

	test( 'nothing is logged while logging is off', async ( { signInAs } ) => {
		await signInAs( 'subscriber' );

		expect( logRows() ).toHaveLength( 0 );
	} );
} );

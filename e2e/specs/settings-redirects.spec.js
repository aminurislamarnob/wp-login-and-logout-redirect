const { test, expect, getOption, setOption } = require( '../fixtures' );

const BASE = 'http://woocommerce.test';

test.describe( 'Redirects tab', () => {
	test( 'saving a login URL persists it', async ( { page, admin } ) => {
		await admin.visitSettings();

		await page
			.getByLabel( 'Login Redirect URL' )
			.fill( `${ BASE }/members/` );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.getByText( 'Settings saved' ).first() ).toBeVisible();
		expect( getOption( 'wplalr_login_redirect' ) ).toBe(
			`${ BASE }/members/`
		);
	} );

	test( 'a saved login URL survives a reload', async ( { page, admin } ) => {
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );

		await admin.visitSettings();

		await expect( page.getByLabel( 'Login Redirect URL' ) ).toHaveValue(
			`${ BASE }/members/`
		);
	} );

	test( 'saving a logout URL persists it', async ( { page, admin } ) => {
		await admin.visitSettings();

		await page
			.getByLabel( 'Logout Redirect URL' )
			.fill( `${ BASE }/goodbye/` );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.getByText( 'Settings saved' ).first() ).toBeVisible();
		expect( getOption( 'wplalr_logout_redirect' ) ).toBe(
			`${ BASE }/goodbye/`
		);
	} );

	test( 'both URLs save together', async ( { page, admin } ) => {
		await admin.visitSettings();

		await page.getByLabel( 'Login Redirect URL' ).fill( `${ BASE }/in/` );
		await page.getByLabel( 'Logout Redirect URL' ).fill( `${ BASE }/out/` );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.getByText( 'Settings saved' ).first() ).toBeVisible();
		expect( getOption( 'wplalr_login_redirect' ) ).toBe( `${ BASE }/in/` );
		expect( getOption( 'wplalr_logout_redirect' ) ).toBe( `${ BASE }/out/` );
	} );

	test( 'clearing a URL saves the empty value', async ( { page, admin } ) => {
		setOption( 'wplalr_login_redirect', `${ BASE }/members/` );

		await admin.visitSettings();

		await page.getByLabel( 'Login Redirect URL' ).fill( '' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.getByText( 'Settings saved' ).first() ).toBeVisible();
		expect( getOption( 'wplalr_login_redirect' ) ).toBe( '' );
	} );

	test( 'a placeholder chip copies its token', async ( {
		page,
		admin,
		context,
	} ) => {
		await context.grantPermissions( [
			'clipboard-read',
			'clipboard-write',
		] );

		await admin.visitSettings();

		await page
			.getByRole( 'button', { name: 'Copy {{username}}' } )
			.first()
			.click();

		await expect( page.getByText( 'Copied!' ).first() ).toBeVisible();

		const clipboard = await page.evaluate( () =>
			navigator.clipboard.readText()
		);

		expect( clipboard ).toBe( '{{username}}' );
	} );

	test( 'every documented placeholder has a chip', async ( {
		page,
		admin,
	} ) => {
		await admin.visitSettings();

		for ( const token of [
			'{{username}}',
			'{{user_slug}}',
			'{{website_url}}',
		] ) {
			await expect(
				page.getByRole( 'button', { name: `Copy ${ token }` } ).first()
			).toBeVisible();
		}
	} );
} );

test.describe( 'admin navigation', () => {
	test( 'the hash tabs switch between Redirects and Rules', async ( {
		page,
		admin,
	} ) => {
		await admin.visitSettings();

		await expect( page.getByLabel( 'Login Redirect URL' ) ).toBeVisible();

		await page.getByRole( 'link', { name: 'Rules' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Redirect Rules' } )
		).toBeVisible();
		await expect( page ).toHaveURL( /#\/rules/ );
	} );

	test( 'a rules deep link opens on the Rules tab', async ( {
		page,
		admin,
	} ) => {
		await admin.visitSettings( '#/rules' );

		await expect(
			page.getByRole( 'heading', { name: 'Redirect Rules' } )
		).toBeVisible();
	} );

	// Each hop starts from a freshly loaded settings page. Chaining the clicks
	// instead — settings to logs to sessions — makes the second click race the
	// destination page's own mount, and it is the same coverage either way.
	for ( const { label, slug } of [
		{ label: 'Audit Logs', slug: 'wplalr_audit_logs' },
		{ label: 'Logged-in Users', slug: 'wplalr_sessions' },
	] ) {
		test( `the cross-page nav reaches ${ label }`, async ( {
			page,
			admin,
			app,
		} ) => {
			await admin.visitSettings();

			// Scoped to the app: the WP sidebar repeats these link names.
			const link = app.getByRole( 'link', { name: label } );

			await expect( link ).toHaveAttribute(
				'href',
				new RegExp( `page=${ slug }` )
			);

			await link.click();

			await expect( page ).toHaveURL( new RegExp( `page=${ slug }` ) );
			await admin.waitForApp();
		} );
	}
} );

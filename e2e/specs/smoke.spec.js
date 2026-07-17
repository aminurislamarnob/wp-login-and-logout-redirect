const { test, expect } = require( '../fixtures' );

test.describe( 'harness', () => {
	test( 'the settings screen mounts the React app', async ( { page, admin } ) => {
		await admin.visitSettings();

		await expect( page.locator( '#wplalr-settings' ) ).toBeVisible();
		await expect(
			page.getByLabel( 'Login Redirect URL' )
		).toBeVisible();
	} );
} );

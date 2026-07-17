const {
	test,
	expect,
	seedSessions,
	sessionCount,
	setOption,
	logRows,
	USERS,
} = require( '../fixtures' );

/**
 * The Logged-in Users screen, driven the way an admin uses it.
 *
 * Sessions are minted through WP_Session_Tokens for the throwaway accounts, so
 * force-logout never touches a session a human is actually using. The one
 * session that must survive everything here is the test admin's own — it is the
 * suite's ticket into wp-admin.
 */
test.describe( 'Logged-in Users page', () => {
	/** The row for one of our throwaway users. */
	const rowFor = ( page, key ) =>
		page.locator( '.wplalr-sessions-table tbody tr', {
			hasText: USERS[ key ].login,
		} );

	test( 'a user with a session is listed with their role', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login );

		await admin.visitSessions();

		const row = rowFor( page, 'editor' );

		await expect( row ).toBeVisible();
		await expect( row ).toContainText( 'Editor' );
		await expect( row ).toContainText( USERS.editor.email );
	} );

	test( 'a user with no session is not listed', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login );

		await admin.visitSessions();

		await expect( rowFor( page, 'editor' ) ).toBeVisible();
		await expect( rowFor( page, 'subscriber' ) ).toHaveCount( 0 );
	} );

	test( 'the admin sees their own row marked You', async ( {
		page,
		admin,
	} ) => {
		await admin.visitSessions();

		const own = rowFor( page, 'admin' );

		await expect( own ).toBeVisible();
		await expect( own.locator( '.wplalr-you-badge' ) ).toHaveText( 'You' );
	} );

	test( 'multiple devices show as the session count', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login, 3 );

		await admin.visitSessions();

		await expect( rowFor( page, 'editor' ) ).toContainText( '3' );
	} );

	test( 'the role filter narrows the list', async ( { page, admin } ) => {
		seedSessions( USERS.editor.login );
		seedSessions( USERS.subscriber.login );

		await admin.visitSessions();

		await page.getByLabel( 'Role' ).selectOption( 'editor' );

		await expect( rowFor( page, 'editor' ) ).toBeVisible();
		await expect( rowFor( page, 'subscriber' ) ).toHaveCount( 0 );
	} );

	test( 'search finds a user by login', async ( { page, admin } ) => {
		seedSessions( USERS.editor.login );
		seedSessions( USERS.subscriber.login );

		await admin.visitSessions();

		await page
			.getByPlaceholder( /Search/ )
			.fill( USERS.subscriber.login );

		await expect( rowFor( page, 'subscriber' ) ).toBeVisible();
		await expect( rowFor( page, 'editor' ) ).toHaveCount( 0 );
	} );

	test( 'logging a user out ends their sessions after a confirm', async ( {
		page,
		admin,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );
		seedSessions( USERS.editor.login, 2 );

		await admin.visitSessions();

		await rowFor( page, 'editor' )
			.getByRole( 'button', { name: 'Log out' } )
			.click();

		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Force logout' } ).click();

		await expect( rowFor( page, 'editor' ) ).toHaveCount( 0 );
		expect( sessionCount( USERS.editor.login ) ).toBe( 0 );

		// The forced logout lands in the audit trail.
		const forced = logRows().filter(
			( r ) => r.event === 'forced_logout'
		);

		expect( forced ).toHaveLength( 1 );
		expect( forced[ 0 ].username ).toBe( USERS.editor.login );
	} );

	test( 'ending one device leaves the others signed in', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login, 3 );

		await admin.visitSessions();

		const row = rowFor( page, 'editor' );

		await row.getByRole( 'button', { name: /Show/ } ).click();
		await page
			.getByRole( 'button', { name: 'End' } )
			.first()
			.click();

		// This is the regression the session-id collision fix exists for: ending
		// one device must never sign out the user's other devices.
		expect( sessionCount( USERS.editor.login ) ).toBe( 2 );
		await expect( rowFor( page, 'editor' ) ).toContainText( '2' );
	} );

	test( 'bulk force logout signs out the selected users only', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login );
		seedSessions( USERS.subscriber.login );
		seedSessions( USERS.customer.login );

		await admin.visitSessions();

		await rowFor( page, 'editor' ).getByRole( 'checkbox' ).check();
		await rowFor( page, 'subscriber' ).getByRole( 'checkbox' ).check();

		await page
			.getByRole( 'button', { name: 'Force logout selected' } )
			.click();

		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Force logout' } ).click();

		await expect( rowFor( page, 'editor' ) ).toHaveCount( 0 );
		await expect( rowFor( page, 'subscriber' ) ).toHaveCount( 0 );

		expect( sessionCount( USERS.editor.login ) ).toBe( 0 );
		expect( sessionCount( USERS.subscriber.login ) ).toBe( 0 );
		expect( sessionCount( USERS.customer.login ) ).toBe( 1 );
	} );

	test( 'force logout everyone spares the acting admin', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login );
		seedSessions( USERS.subscriber.login );

		await admin.visitSessions();

		await page
			.getByRole( 'button', { name: 'Force logout everyone' } )
			.click();

		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Force logout' } )
			.click();

		await expect( rowFor( page, 'editor' ) ).toHaveCount( 0 );
		expect( sessionCount( USERS.editor.login ) ).toBe( 0 );
		expect( sessionCount( USERS.subscriber.login ) ).toBe( 0 );

		// The admin pressing the button must still be signed in — both in the
		// session store and in practice.
		expect(
			sessionCount( USERS.admin.login )
		).toBeGreaterThanOrEqual( 1 );

		await page.goto( '/wp-admin/' );
		await expect( page.locator( 'body.wp-admin' ) ).toBeVisible();
	} );

	test( 'cancelling the confirm leaves everyone signed in', async ( {
		page,
		admin,
	} ) => {
		seedSessions( USERS.editor.login );

		await admin.visitSessions();

		await rowFor( page, 'editor' )
			.getByRole( 'button', { name: 'Log out' } )
			.click();

		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Cancel' } ).click();

		expect( sessionCount( USERS.editor.login ) ).toBe( 1 );
	} );
} );

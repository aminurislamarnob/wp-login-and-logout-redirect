const {
	test,
	expect,
	setOption,
	getOption,
	seedLogRow,
	logRows,
} = require( '../fixtures' );

test.describe( 'Audit Logs page', () => {
	test.beforeEach( () => {
		// The page renders regardless, but seeded rows are only meaningful with
		// logging on, and the Others tab reads this too.
		setOption( 'wplalr_enable_logs', 'yes' );
	} );

	test( 'the stat cards count the events', async ( { page, admin } ) => {
		seedLogRow( { event: 'login', username: 'a' } );
		seedLogRow( { event: 'login', username: 'b' } );
		seedLogRow( { event: 'logout', username: 'a' } );
		seedLogRow( { event: 'failed', status: 'failed', username: 'x' } );

		await admin.visitLogs();

		// Exact label match: hasText is a substring check, and 'Logins' would
		// also match the 'Failed logins' card.
		const cardValue = ( label ) =>
			page
				.locator( '.wplalr-stat-info', {
					has: page.getByText( label, { exact: true } ),
				} )
				.locator( '.wplalr-stat-value' );

		await expect( cardValue( 'Logins' ) ).toHaveText( '2' );
		await expect( cardValue( 'Logouts' ) ).toHaveText( '1' );
		await expect( cardValue( 'Failed logins' ) ).toHaveText( '1' );
		await expect( cardValue( 'Total events' ) ).toHaveText( '4' );
	} );

	test( 'a row shows the username, event and IP', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( {
			event: 'login',
			username: 'zoe',
			ip: '203.0.113.99',
		} );

		await admin.visitLogs();

		const row = page.locator( '.wplalr-logs-table tbody tr' );

		await expect( row ).toHaveCount( 1 );
		await expect( row ).toContainText( 'zoe' );
		await expect( row ).toContainText( '203.0.113.99' );
		await expect( row.locator( '.wplalr-event-badge' ) ).toHaveText(
			'Login'
		);
	} );

	test( 'searching narrows the table to the matching user', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( { event: 'login', username: 'zoe' } );
		seedLogRow( { event: 'login', username: 'bob' } );

		await admin.visitLogs();
		await expect(
			page.locator( '.wplalr-logs-table tbody tr' )
		).toHaveCount( 2 );

		await page.getByPlaceholder( 'Search username or IP…' ).fill( 'zoe' );

		await expect(
			page.locator( '.wplalr-logs-table tbody tr' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '.wplalr-logs-table tbody' )
		).toContainText( 'zoe' );
	} );

	test( 'the event filter shows only that event', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( { event: 'login', username: 'zoe' } );
		seedLogRow( { event: 'failed', status: 'failed', username: 'mallory' } );

		await admin.visitLogs();

		await page.getByLabel( 'Event' ).selectOption( 'failed' );

		const rows = page.locator( '.wplalr-logs-table tbody tr' );

		await expect( rows ).toHaveCount( 1 );
		await expect( rows ).toContainText( 'mallory' );
	} );

	test( 'pagination pages through the rows', async ( { page, admin } ) => {
		for ( let i = 0; i < 25; i++ ) {
			seedLogRow( { event: 'login', username: `user${ i }` } );
		}

		await admin.visitLogs();

		await expect( page.locator( '.wplalr-logs-pagination' ) ).toBeVisible();
		await expect( page.locator( '.wplalr-logs-page-info' ) ).toContainText(
			'1 / 2'
		);

		await page.getByRole( 'button', { name: 'Next' } ).click();

		await expect( page.locator( '.wplalr-logs-page-info' ) ).toContainText(
			'2 / 2'
		);
		await expect(
			page.locator( '.wplalr-logs-table tbody tr' )
		).toHaveCount( 5 );
	} );

	test( 'deleting a single entry removes only that row', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( { event: 'login', username: 'keep-me' } );
		seedLogRow( { event: 'login', username: 'delete-me' } );

		await admin.visitLogs();

		await page
			.locator( 'tr', { hasText: 'delete-me' } )
			.getByRole( 'button', { name: 'Delete entry' } )
			.click();

		await expect(
			page.locator( '.wplalr-logs-table tbody tr' )
		).toHaveCount( 1 );

		const remaining = logRows();

		expect( remaining ).toHaveLength( 1 );
		expect( remaining[ 0 ].username ).toBe( 'keep-me' );
	} );

	test( 'Delete all asks for confirmation and clears the log', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( { event: 'login', username: 'a' } );
		seedLogRow( { event: 'logout', username: 'b' } );

		await admin.visitLogs();

		await page.getByRole( 'button', { name: 'Delete all' } ).click();

		// The destructive step must sit behind a modal, not fire directly.
		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Delete all' } ).click();

		await expect(
			page.locator( '.wplalr-logs-table tbody tr' )
		).toHaveCount( 0 );
		expect( logRows() ).toHaveLength( 0 );
	} );

	test( 'cancelling the Delete all modal keeps every row', async ( {
		page,
		admin,
	} ) => {
		seedLogRow( { event: 'login', username: 'a' } );

		await admin.visitLogs();

		await page.getByRole( 'button', { name: 'Delete all' } ).click();

		const modal = page.getByRole( 'dialog' );

		await expect( modal ).toBeVisible();
		await modal.getByRole( 'button', { name: 'Cancel' } ).click();

		expect( logRows() ).toHaveLength( 1 );
	} );
} );

test.describe( 'Others tab (log settings)', () => {
	test( 'enabling logging saves the option', async ( { page, admin } ) => {
		await admin.visitSettings( '#/others' );

		await page.getByRole( 'checkbox', { name: 'Enable logging' } ).click();
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect(
			page.getByText( 'Settings saved' ).first()
		).toBeVisible();

		expect( getOption( 'wplalr_enable_logs' ) ).toBe( 'yes' );
	} );

	test( 'the retention window saves', async ( { page, admin } ) => {
		setOption( 'wplalr_enable_logs', 'yes' );

		await admin.visitSettings( '#/others' );

		await page
			.getByLabel( 'Delete logs older than' )
			.selectOption( '90' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect(
			page.getByText( 'Settings saved' ).first()
		).toBeVisible();

		// Options come back from the DB as strings regardless of what was stored.
		expect( Number( getOption( 'wplalr_logs_retention_days' ) ) ).toBe( 90 );
	} );

	test( 'the digest cadence saves and schedules the cron', async ( {
		page,
		admin,
	} ) => {
		setOption( 'wplalr_enable_logs', 'yes' );

		await admin.visitSettings( '#/others' );

		await page.getByLabel( 'Digest summary' ).selectOption( 'weekly' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect(
			page.getByText( 'Settings saved' ).first()
		).toBeVisible();

		expect( getOption( 'wplalr_logs_digest' ) ).toBe( 'weekly' );

		const { evalPhp } = require( '../utils/wp-cli' );

		expect(
			evalPhp( "echo wp_get_schedule( 'wplalr_logs_digest_send' );" )
		).toBe( 'weekly' );
	} );

	test( 'the notification email saves', async ( { page, admin } ) => {
		setOption( 'wplalr_enable_logs', 'yes' );

		await admin.visitSettings( '#/others' );

		await page
			.getByLabel( 'Notification email' )
			.fill( 'security@example.com' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect(
			page.getByText( 'Settings saved' ).first()
		).toBeVisible();

		expect( getOption( 'wplalr_logs_notification_email' ) ).toBe(
			'security@example.com'
		);
	} );
} );

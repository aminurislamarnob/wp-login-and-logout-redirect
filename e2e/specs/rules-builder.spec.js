const {
	test,
	expect,
	getOption,
	setOption,
	makeRule,
	userId,
	USERS,
} = require( '../fixtures' );

const BASE = 'http://woocommerce.test';

/**
 * Open the Rules tab.
 *
 * @param {Object} admin Admin fixture.
 * @return {Promise<void>}
 */
const visitRules = ( admin ) => admin.visitSettings( '#/rules' );

/** A rule card by its visible name. */
const card = ( page, name ) =>
	page.locator( '.wplalr-rule-card' ).filter( { hasText: name } );

test.describe( 'rule builder', () => {
	test( 'the empty state invites a first rule', async ( { page, admin } ) => {
		await visitRules( admin );

		await expect( page.locator( '.wplalr-rules-empty' ) ).toBeVisible();
	} );

	test( 'adding and saving a rule persists it', async ( { page, admin } ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();

		await page.getByLabel( 'Rule name' ).fill( 'Editors go to the desk' );
		await page
			.getByLabel( 'Login redirect URL' )
			.fill( `${ BASE }/editor-desk/` );

		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		const rules = getOption( 'wplalr_redirect_rules' );

		expect( rules ).toHaveLength( 1 );
		expect( rules[ 0 ].label ).toBe( 'Editors go to the desk' );
		expect( rules[ 0 ].login_url ).toBe( `${ BASE }/editor-desk/` );
		expect( rules[ 0 ].enabled ).toBe( true );
	} );

	test( 'a saved rule reloads with its values', async ( { page, admin } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				label: 'Reloaded rule',
				login_url: `${ BASE }/in/`,
				logout_url: `${ BASE }/out/`,
			} ),
		] );

		await visitRules( admin );
		await card( page, 'Reloaded rule' )
			.getByRole( 'button', { name: 'Expand rule' } )
			.click();

		await expect( page.getByLabel( 'Rule name' ) ).toHaveValue(
			'Reloaded rule'
		);
		await expect( page.getByLabel( 'Login redirect URL' ) ).toHaveValue(
			`${ BASE }/in/`
		);
		await expect( page.getByLabel( 'Logout redirect URL' ) ).toHaveValue(
			`${ BASE }/out/`
		);
	} );

	test( 'a logout-only rule saves', async ( { page, admin } ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page.getByLabel( 'Rule name' ).fill( 'Bye' );
		await page
			.getByLabel( 'Logout redirect URL' )
			.fill( `${ BASE }/goodbye/` );
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();

		const rules = getOption( 'wplalr_redirect_rules' );

		expect( rules[ 0 ].logout_url ).toBe( `${ BASE }/goodbye/` );
		expect( rules[ 0 ].login_url ).toBe( '' );
	} );

	test( 'a rule with no conditions says it applies to everyone', async ( {
		page,
		admin,
	} ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();

		await expect(
			page.getByText( 'Applies to everyone' ).first()
		).toBeVisible();
	} );

	test( 'deleting a rule removes it', async ( { page, admin } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { label: 'Doomed rule', login_url: `${ BASE }/x/` } ),
		] );

		await visitRules( admin );

		await card( page, 'Doomed rule' )
			.getByRole( 'button', { name: 'Delete rule' } )
			.click();
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		expect( getOption( 'wplalr_redirect_rules' ) ).toEqual( [] );
	} );

	test( 'toggling a rule off saves it disabled', async ( { page, admin } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { label: 'Switchable', login_url: `${ BASE }/x/` } ),
		] );

		await visitRules( admin );

		await card( page, 'Switchable' )
			.getByRole( 'checkbox', { name: 'Enabled' } )
			.click();
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		expect( getOption( 'wplalr_redirect_rules' )[ 0 ].enabled ).toBe(
			false
		);
	} );
} );

test.describe( 'rule conditions', () => {
	test( 'a role condition saves', async ( { page, admin } ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page.getByLabel( 'Rule name' ).fill( 'Editors' );
		await page.getByRole( 'button', { name: 'Add condition' } ).click();

		await page.getByLabel( 'When' ).selectOption( 'role' );
		await page.getByLabel( 'Roles' ).fill( 'Editor' );
		await page.getByLabel( 'Roles' ).press( 'Enter' );

		await page
			.getByLabel( 'Login redirect URL' )
			.fill( `${ BASE }/editor-desk/` );
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		const [ rule ] = getOption( 'wplalr_redirect_rules' );

		expect( rule.conditions ).toEqual( [
			{ type: 'role', values: [ 'editor' ] },
		] );
	} );

	test( 'a capability condition saves', async ( { page, admin } ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page.getByLabel( 'Rule name' ).fill( 'Can edit' );
		await page.getByRole( 'button', { name: 'Add condition' } ).click();

		await page.getByLabel( 'When' ).selectOption( 'capability' );
		await page.getByLabel( 'Capabilities' ).fill( 'edit_others_posts' );
		await page.getByLabel( 'Capabilities' ).press( 'Enter' );

		await page.getByLabel( 'Login redirect URL' ).fill( `${ BASE }/can/` );
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		const [ rule ] = getOption( 'wplalr_redirect_rules' );

		expect( rule.conditions ).toEqual( [
			{ type: 'capability', values: [ 'edit_others_posts' ] },
		] );
	} );

	test( 'a specific-user condition saves', async ( { page, admin } ) => {
		const id = userId( USERS.subscriber.login );

		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page.getByLabel( 'Rule name' ).fill( 'One person' );
		await page.getByRole( 'button', { name: 'Add condition' } ).click();

		await page.getByLabel( 'When' ).selectOption( 'user' );

		// The field stores ids but its tokens read "Display Name (#id)", so a
		// suggestion has to be picked — typed text alone parses to no id at all.
		await page.getByLabel( 'Users' ).fill( USERS.subscriber.login );
		await page
			.getByRole( 'option', { name: new RegExp( `#${ id }\\)` ) } )
			.click();

		await page.getByLabel( 'Login redirect URL' ).fill( `${ BASE }/vip/` );
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		const [ rule ] = getOption( 'wplalr_redirect_rules' );

		expect( rule.conditions ).toEqual( [
			{ type: 'user', values: [ String( id ) ] },
		] );
	} );

	test( 'two conditions are joined with AND', async ( { page, admin } ) => {
		await visitRules( admin );

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page.getByLabel( 'Rule name' ).fill( 'Both' );

		await page.getByRole( 'button', { name: 'Add condition' } ).click();
		await page.getByLabel( 'When' ).first().selectOption( 'role' );
		await page.getByLabel( 'Roles' ).fill( 'Editor' );
		await page.getByLabel( 'Roles' ).press( 'Enter' );

		await page.getByRole( 'button', { name: 'Add condition' } ).click();
		await page.getByLabel( 'When' ).nth( 1 ).selectOption( 'capability' );
		await page.getByLabel( 'Capabilities' ).fill( 'edit_others_posts' );
		await page.getByLabel( 'Capabilities' ).press( 'Enter' );

		await expect( page.getByText( 'AND' ).first() ).toBeVisible();

		await page.getByLabel( 'Login redirect URL' ).fill( `${ BASE }/both/` );
		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		expect( getOption( 'wplalr_redirect_rules' )[ 0 ].conditions ).toEqual( [
			{ type: 'role', values: [ 'editor' ] },
			{ type: 'capability', values: [ 'edit_others_posts' ] },
		] );
	} );

	test( 'removing a condition drops it', async ( { page, admin } ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( {
				label: 'Trim me',
				login_url: `${ BASE }/x/`,
				conditions: [ { type: 'role', values: [ 'editor' ] } ],
			} ),
		] );

		await visitRules( admin );
		await card( page, 'Trim me' )
			.getByRole( 'button', { name: 'Expand rule' } )
			.click();

		await page
			.getByRole( 'button', { name: 'Remove condition' } )
			.click();

		await expect(
			page.getByText(
				'No conditions — this rule applies to everyone. Add a condition to target specific users.'
			)
		).toBeVisible();
	} );
} );

test.describe( 'rule priority', () => {
	test( 'rules are numbered in evaluation order', async ( {
		page,
		admin,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { label: 'First rule', login_url: `${ BASE }/1/` } ),
			makeRule( { label: 'Second rule', login_url: `${ BASE }/2/` } ),
		] );

		await visitRules( admin );

		const priorities = page.locator( '.wplalr-rule-priority' );

		await expect( priorities.nth( 0 ) ).toHaveText( '1' );
		await expect( priorities.nth( 1 ) ).toHaveText( '2' );

		await expect(
			page.locator( '.wplalr-rule-card' ).nth( 0 )
		).toContainText( 'First rule' );
	} );

	test( 'dragging a rule to the top makes it win', async ( {
		page,
		admin,
	} ) => {
		setOption( 'wplalr_redirect_rules', [
			makeRule( { label: 'First rule', login_url: `${ BASE }/1/` } ),
			makeRule( { label: 'Second rule', login_url: `${ BASE }/2/` } ),
		] );

		await visitRules( admin );

		const handles = page.getByRole( 'button', { name: 'Reorder rule' } );

		// dnd-kit's KeyboardSensor: space picks up, arrows move, space drops.
		// That is the accessible path and far less flaky than synthesising
		// pointer moves.
		//
		// The presses need a beat between them, and there is nothing honest to
		// poll for: dnd-kit's pick-up announcement already reads "...was moved
		// over droppable area...", so waiting on the live region matches the
		// previous step's text and waits for nothing.
		await handles.nth( 1 ).focus();

		await page.keyboard.press( 'Space' );
		await page.waitForTimeout( 300 );

		await page.keyboard.press( 'ArrowUp' );
		await page.waitForTimeout( 300 );

		await page.keyboard.press( 'Space' );

		await expect(
			page.locator( '.wplalr-rule-title strong' ).nth( 0 )
		).toHaveText( 'Second rule' );

		await page.getByRole( 'button', { name: 'Save Rules' } ).click();
		await expect( page.getByText( 'Redirect rules saved' ).first() ).toBeVisible();

		const rules = getOption( 'wplalr_redirect_rules' );

		expect( rules[ 0 ].label ).toBe( 'Second rule' );
		expect( rules[ 1 ].label ).toBe( 'First rule' );
	} );
} );

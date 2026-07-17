/**
 * Provisions the real dev site for the run, and records enough to undo it.
 *
 * This suite targets a live Herd site that has other plugins and real content on
 * it, so setup is deliberately conservative: it creates only prefixed throwaway
 * accounts, records the plugin's prior active state and option values, and
 * refuses to run at all if another login-redirect plugin is active and would
 * fight ours over the same hooks.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );
const { wp, getOption, deleteOption } = require( './utils/wp-cli' );
const { USERS, PASSWORD, PLUGIN_OPTIONS } = require( './utils/constants' );

const PLUGIN_SLUG = 'wp-login-and-logout-redirect';
const STATE_DIR = path.join( __dirname, '.state' );
const BACKUP_DIR = path.join( __dirname, '.backups' );

/** Other plugins that hook the same login/logout redirect filters. */
const CONFLICTING = [ 'peters-login-redirect', 'sky-login-redirect' ];

module.exports = async ( config ) => {
	fs.mkdirSync( STATE_DIR, { recursive: true } );

	const baseURL = config.projects[ 0 ].use.baseURL;

	// 1. Refuse to run against a site where another redirect plugin would race
	// ours for the same filters — the failures would be baffling.
	const active = wp( [ 'plugin', 'list', '--status=active', '--field=name' ] )
		.split( '\n' )
		.map( ( n ) => n.trim() );

	const clashes = CONFLICTING.filter( ( slug ) => active.includes( slug ) );

	if ( clashes.length ) {
		throw new Error(
			`Refusing to run: ${ clashes.join( ', ' ) } also hooks login_redirect. ` +
				'Deactivate it first, or the redirect journeys will be testing the wrong plugin.'
		);
	}

	// 2. Record what we are about to change, so teardown can put it back.
	//
	// A run that dies before teardown leaves this file behind — and by then the
	// site is already mutated. Re-snapshotting would record our own changes as
	// the site's "original" state and make them permanent, so an existing file
	// always wins.
	const restorePath = path.join( STATE_DIR, 'restore.json' );

	if ( ! fs.existsSync( restorePath ) ) {
		const restore = {
			takenAt: new Date().toISOString(),
			pluginWasActive: active.includes( PLUGIN_SLUG ),
			options: {},
		};

		for ( const name of PLUGIN_OPTIONS ) {
			restore.options[ name ] = getOption( name );
		}

		const serialised = JSON.stringify( restore, null, 2 );

		fs.writeFileSync( restorePath, serialised );

		// A second copy that teardown never deletes. restore.json is consumed on a
		// clean finish, which means a run killed mid-flight — Ctrl-C, a crash, a
		// stray pkill — can leave the site wiped with nothing to restore from.
		// These backups are the only durable record; `npm run e2e:restore` replays
		// the newest one.
		fs.mkdirSync( BACKUP_DIR, { recursive: true } );
		fs.writeFileSync(
			path.join(
				BACKUP_DIR,
				`options-${ restore.takenAt.replace( /[:.]/g, '-' ) }.json`
			),
			serialised
		);
	}

	const restore = JSON.parse( fs.readFileSync( restorePath, 'utf8' ) );

	// 3. Activate the plugin under test, then clear its options. The site may
	// already carry a working configuration, and this plugin's whole job is to
	// redirect logins somewhere else — including the one on the next line.
	if ( ! restore.pluginWasActive ) {
		wp( [ 'plugin', 'activate', PLUGIN_SLUG ] );
	}

	for ( const name of PLUGIN_OPTIONS ) {
		deleteOption( name );
	}

	// 4. Create the throwaway accounts. Deleted first so a crashed previous run
	// cannot leave a stale password behind.
	for ( const user of Object.values( USERS ) ) {
		const existing = wp( [ 'user', 'get', user.login, '--field=ID' ], {
			allowFailure: true,
		} );

		if ( existing ) {
			wp( [ 'user', 'delete', existing, '--yes', '--reassign=0' ] );
		}

		wp( [
			'user',
			'create',
			user.login,
			user.email,
			`--role=${ user.role }`,
			`--user_pass=${ PASSWORD }`,
			'--porcelain',
		] );
	}

	// 5. Sign the throwaway admin in once and cache the cookies. Every admin-UI
	// spec reuses this instead of replaying the login form.
	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', USERS.admin.login );
	await page.fill( '#user_pass', PASSWORD );

	// Wait for the form to submit, not for a particular landing page: where a
	// login lands is exactly what this plugin changes, so asserting on it here
	// would couple setup to the thing under test.
	await Promise.all( [
		page.waitForURL( ( url ) => ! url.pathname.endsWith( '/wp-login.php' ) ),
		page.click( '#wp-submit' ),
	] );

	// Prove the cookie actually works rather than trusting the redirect.
	await page.goto( '/wp-admin/' );

	if ( ! ( await page.locator( 'body.wp-admin' ).count() ) ) {
		await browser.close();
		throw new Error(
			`Could not sign in as ${ USERS.admin.login }; landed on ${ page.url() }`
		);
	}

	await page
		.context()
		.storageState( { path: path.join( STATE_DIR, 'admin.json' ) } );

	await browser.close();
};

/**
 * Puts the dev site back the way global-setup found it.
 *
 * Every step is best-effort and independent: a failure restoring one thing must
 * not strand the rest. Leaving a real site dirty is worse than a noisy teardown.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { wp, setOption, deleteOption } = require( './utils/wp-cli' );
const { USERS, PLUGIN_OPTIONS } = require( './utils/constants' );

const PLUGIN_SLUG = 'wp-login-and-logout-redirect';
const STATE_DIR = path.join( __dirname, '.state' );

module.exports = async () => {
	const restorePath = path.join( STATE_DIR, 'restore.json' );

	if ( ! fs.existsSync( restorePath ) ) {
		return;
	}

	const restore = JSON.parse( fs.readFileSync( restorePath, 'utf8' ) );
	const problems = [];

	// 1. Remove the throwaway accounts.
	for ( const user of Object.values( USERS ) ) {
		try {
			const id = wp( [ 'user', 'get', user.login, '--field=ID' ], {
				allowFailure: true,
			} );

			if ( id ) {
				wp( [ 'user', 'delete', id, '--yes', '--reassign=0' ] );
			}
		} catch ( error ) {
			problems.push( `user ${ user.login }: ${ error.message }` );
		}
	}

	// 2. Put the plugin's options back to their prior values.
	for ( const name of PLUGIN_OPTIONS ) {
		try {
			const previous = restore.options[ name ];

			if ( null === previous ) {
				deleteOption( name );
			} else {
				setOption( name, previous );
			}
		} catch ( error ) {
			problems.push( `option ${ name }: ${ error.message }` );
		}
	}

	// 3. Leave the plugin as we found it.
	try {
		if ( ! restore.pluginWasActive ) {
			wp( [ 'plugin', 'deactivate', PLUGIN_SLUG ] );
		}
	} catch ( error ) {
		problems.push( `plugin state: ${ error.message }` );
	}

	fs.rmSync( STATE_DIR, { recursive: true, force: true } );

	if ( problems.length ) {
		// Surfaced loudly: this ran against a real site, so anything left behind
		// is something a human needs to know about.
		throw new Error(
			`e2e teardown left the site dirty:\n  ${ problems.join( '\n  ' ) }`
		);
	}
};

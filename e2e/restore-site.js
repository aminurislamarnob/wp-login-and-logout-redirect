/**
 * Standalone recovery: put the site's plugin options back from the newest
 * backup snapshot.
 *
 * Teardown handles the clean-finish path; this exists for the other one — a run
 * killed mid-flight (Ctrl-C, crash, pkill) has already wiped the options and
 * deleted nothing, and `.state/restore.json` may or may not have survived.
 * Every run's first snapshot is also copied to `.backups/`, which nothing
 * deletes, so the newest backup is always the site's true pre-suite state.
 *
 * Usage: node e2e/restore-site.js  (or `npm run e2e:restore`)
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { wp, setOption, deleteOption } = require( './utils/wp-cli' );
const { USERS, PLUGIN_OPTIONS } = require( './utils/constants' );

const PLUGIN_SLUG = 'wp-login-and-logout-redirect';
const STATE_DIR = path.join( __dirname, '.state' );
const BACKUP_DIR = path.join( __dirname, '.backups' );

function newestSnapshot() {
	const restorePath = path.join( STATE_DIR, 'restore.json' );

	if ( fs.existsSync( restorePath ) ) {
		return restorePath;
	}

	if ( ! fs.existsSync( BACKUP_DIR ) ) {
		return null;
	}

	const backups = fs
		.readdirSync( BACKUP_DIR )
		.filter( ( f ) => f.endsWith( '.json' ) )
		.sort();

	return backups.length
		? path.join( BACKUP_DIR, backups[ backups.length - 1 ] )
		: null;
}

const snapshotPath = newestSnapshot();

if ( ! snapshotPath ) {
	console.error(
		'No snapshot found in e2e/.state or e2e/.backups — nothing to restore from.'
	);
	process.exit( 1 );
}

const snapshot = JSON.parse( fs.readFileSync( snapshotPath, 'utf8' ) );

console.log( `Restoring from ${ snapshotPath }` );

for ( const name of PLUGIN_OPTIONS ) {
	const value = snapshot.options[ name ];

	if ( null === value || undefined === value ) {
		deleteOption( name );
		console.log( `  ${ name } -> (unset)` );
	} else {
		setOption( name, value );
		console.log( `  ${ name } -> restored` );
	}
}

for ( const user of Object.values( USERS ) ) {
	const id = wp( [ 'user', 'get', user.login, '--field=ID' ], {
		allowFailure: true,
	} );

	if ( id ) {
		wp( [ 'user', 'delete', id, '--yes', '--reassign=0' ] );
		console.log( `  deleted user ${ user.login }` );
	}
}

if ( ! snapshot.pluginWasActive ) {
	wp( [ 'plugin', 'deactivate', PLUGIN_SLUG ], { allowFailure: true } );
	console.log( `  ${ PLUGIN_SLUG } -> deactivated` );
}

fs.rmSync( STATE_DIR, { recursive: true, force: true } );

console.log( 'Site restored.' );

/**
 * wp-cli wrapper.
 *
 * The suite runs against a real Herd site rather than a disposable container, so
 * every bit of setup goes through wp-cli: it is deterministic, it needs no admin
 * password, and it keeps the blast radius to things we can name and undo.
 */
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

/** Absolute path to the WordPress install that hosts the plugin. */
const SITE_DIR = path.resolve( __dirname, '../../../../..' );

/**
 * Run a wp-cli command and return trimmed stdout.
 *
 * @param {string[]} args wp-cli arguments.
 * @param {Object}   opts Options.
 * @param {boolean}  opts.allowFailure Resolve to '' instead of throwing.
 * @return {string} stdout.
 */
function wp( args, { allowFailure = false } = {} ) {
	try {
		return execFileSync( 'wp', args, {
			cwd: SITE_DIR,
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} ).trim();
	} catch ( error ) {
		if ( allowFailure ) {
			return '';
		}

		const detail = ( error.stderr || error.message || '' ).toString().trim();
		throw new Error( `wp ${ args.join( ' ' ) }\n${ detail }` );
	}
}

/**
 * Read an option, parsed as JSON where possible.
 *
 * @param {string} name Option name.
 * @return {*} The option value, or null when unset.
 */
function getOption( name ) {
	const raw = wp( [ 'option', 'get', name, '--format=json' ], {
		allowFailure: true,
	} );

	if ( '' === raw ) {
		return null;
	}

	try {
		return JSON.parse( raw );
	} catch ( e ) {
		return raw;
	}
}

/**
 * Write an option as JSON.
 *
 * @param {string} name  Option name.
 * @param {*}      value Value.
 * @return {void}
 */
function setOption( name, value ) {
	execFileSync( 'wp', [ 'option', 'update', name, '--format=json' ], {
		cwd: SITE_DIR,
		input: JSON.stringify( value ),
		encoding: 'utf8',
		stdio: [ 'pipe', 'ignore', 'pipe' ],
	} );
}

/**
 * Delete an option.
 *
 * @param {string} name Option name.
 * @return {void}
 */
function deleteOption( name ) {
	wp( [ 'option', 'delete', name ], { allowFailure: true } );
}

/**
 * Run arbitrary PHP in the loaded WordPress context.
 *
 * Used for the few things wp-cli has no verb for — seeding log rows, minting
 * session tokens — so the tests never hand-roll SQL against the site.
 *
 * @param {string} code PHP source, without the opening tag.
 * @return {string} stdout.
 */
function evalPhp( code ) {
	try {
		return execFileSync( 'wp', [ 'eval', code ], {
			cwd: SITE_DIR,
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} ).trim();
	} catch ( error ) {
		// execFileSync's default message is just "Command failed", which hides
		// the PHP fatal that actually explains the failure.
		const detail = [ error.stdout, error.stderr ]
			.map( ( s ) => ( s || '' ).toString().trim() )
			.filter( Boolean )
			.join( '\n' );

		throw new Error( `wp eval failed:\n${ code }\n---\n${ detail }` );
	}
}

module.exports = { wp, getOption, setOption, deleteOption, evalPhp, SITE_DIR };

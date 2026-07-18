/**
 * Internal dependencies
 */
import { makeId, emptyCondition, emptyRule } from '../utils';

const setCrypto = ( value ) =>
	Object.defineProperty( window, 'crypto', {
		value,
		configurable: true,
	} );

describe( 'makeId', () => {
	const originalCrypto = window.crypto;

	afterEach( () => {
		setCrypto( originalCrypto );
	} );

	it( 'uses crypto.randomUUID when available', () => {
		setCrypto( { randomUUID: jest.fn( () => 'uuid-from-crypto' ) } );

		expect( makeId() ).toBe( 'uuid-from-crypto' );
		expect( window.crypto.randomUUID ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'falls back to a prefixed id when crypto.randomUUID is missing', () => {
		setCrypto( undefined );

		expect( makeId() ).toMatch( /^wplalr-\d+-[a-z0-9]{1,8}$/ );
	} );

	it( 'generates unique ids', () => {
		const ids = new Set( Array.from( { length: 100 }, () => makeId() ) );

		expect( ids.size ).toBe( 100 );
	} );
} );

describe( 'emptyCondition', () => {
	it( 'creates a role condition with no values and a unique id', () => {
		const first = emptyCondition();
		const second = emptyCondition();

		expect( first ).toEqual( {
			id: expect.any( String ),
			type: 'role',
			values: [],
		} );
		expect( first.id ).not.toBe( second.id );
	} );
} );

describe( 'emptyRule', () => {
	it( 'creates an enabled rule with empty fields and a unique id', () => {
		const first = emptyRule();
		const second = emptyRule();

		expect( first ).toEqual( {
			id: expect.any( String ),
			enabled: true,
			label: '',
			conditions: [],
			login_url: '',
			logout_url: '',
		} );
		expect( first.id ).not.toBe( second.id );
	} );
} );

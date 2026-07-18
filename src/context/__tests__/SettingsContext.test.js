/**
 * External dependencies
 */
import { render, screen, act, waitFor } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { select, dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { SettingsProvider, useSettings } from '../SettingsContext';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

const getNotice = ( id ) =>
	select( noticesStore )
		.getNotices()
		.find( ( notice ) => notice.id === id );

// Captures the latest context value so tests can call saveSettings directly.
let latest;

const Consumer = () => {
	latest = useSettings();

	return (
		<div>
			<span data-testid="loading">{ String( latest.isLoading ) }</span>
			<span data-testid="saving">{ String( latest.isSaving ) }</span>
			<span data-testid="login-url">
				{ latest.settings.wplalr_login_redirect ?? '' }
			</span>
		</div>
	);
};

const renderProvider = () =>
	render(
		<SettingsProvider>
			<Consumer />
		</SettingsProvider>
	);

describe( 'SettingsProvider', () => {
	beforeEach( () => {
		latest = undefined;
		jest.clearAllMocks();
		dispatch( noticesStore ).removeAllNotices();
	} );

	it( 'fetches settings on mount and exposes them', async () => {
		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/dashboard/',
		} );

		renderProvider();

		expect( screen.getByTestId( 'loading' ) ).toHaveTextContent( 'true' );

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wplalr/v1/settings',
		} );
		expect( screen.getByTestId( 'login-url' ) ).toHaveTextContent(
			'https://example.test/dashboard/'
		);
	} );

	it( 'shows an error snackbar when the initial fetch fails', async () => {
		apiFetch.mockRejectedValueOnce( { message: 'Request failed' } );

		renderProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		const notice = getNotice( 'wplalr-fetch-error' );
		expect( notice ).toBeDefined();
		expect( notice.content ).toBe( 'Request failed' );
		expect( notice.type ).toBe( 'snackbar' );
		expect( notice.status ).toBe( 'error' );
	} );

	it( 'saves settings via POST and shows a success snackbar', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/saved/',
		} );

		await act( () =>
			latest.saveSettings( {
				wplalr_login_redirect: 'https://example.test/saved/',
			} )
		);

		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/wplalr/v1/settings',
			method: 'POST',
			data: {
				wplalr_login_redirect: 'https://example.test/saved/',
			},
		} );
		expect( screen.getByTestId( 'login-url' ) ).toHaveTextContent(
			'https://example.test/saved/'
		);
		const notice = getNotice( 'wplalr-save-success' );
		expect( notice ).toBeDefined();
		expect( notice.content ).toBe( 'Settings saved successfully!' );
		expect( notice.type ).toBe( 'snackbar' );
		expect( notice.status ).toBe( 'success' );
		expect( notice.isDismissible ).toBe( false );
	} );

	it( 'toggles isSaving while a save is in flight', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		let resolveSave;
		apiFetch.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveSave = resolve;
				} )
		);

		let savePromise;
		act( () => {
			savePromise = latest.saveSettings( {} );
		} );

		expect( screen.getByTestId( 'saving' ) ).toHaveTextContent( 'true' );

		await act( async () => {
			resolveSave( {} );
			await savePromise;
		} );

		expect( screen.getByTestId( 'saving' ) ).toHaveTextContent( 'false' );
	} );

	it( 'uses a custom success message when provided', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		apiFetch.mockResolvedValueOnce( {} );

		await act( () => latest.saveSettings( {}, 'Rule deleted.' ) );

		expect( getNotice( 'wplalr-save-success' ).content ).toBe(
			'Rule deleted.'
		);
	} );

	it( 'shows an error snackbar and clears isSaving when the save fails', async () => {
		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/original/',
		} );

		renderProvider();

		await waitFor( () =>
			expect( screen.getByTestId( 'loading' ) ).toHaveTextContent(
				'false'
			)
		);

		apiFetch.mockRejectedValueOnce( { message: 'Save failed' } );

		await act( () => latest.saveSettings( {} ) );

		const notice = getNotice( 'wplalr-save-error' );
		expect( notice ).toBeDefined();
		expect( notice.content ).toBe( 'Save failed' );
		expect( notice.status ).toBe( 'error' );
		expect( screen.getByTestId( 'saving' ) ).toHaveTextContent( 'false' );
		// The previously fetched settings are left untouched.
		expect( screen.getByTestId( 'login-url' ) ).toHaveTextContent(
			'https://example.test/original/'
		);
	} );
} );

describe( 'useSettings', () => {
	it( 'throws when used outside a SettingsProvider', () => {
		// Silence React's error-boundary logging for the intentional throw.
		const consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );

		expect( () => render( <Consumer /> ) ).toThrow(
			'useSettings must be used within a SettingsProvider'
		);

		consoleError.mockRestore();
	} );
} );

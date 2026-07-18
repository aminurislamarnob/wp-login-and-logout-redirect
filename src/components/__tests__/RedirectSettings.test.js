/**
 * External dependencies
 */
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { SettingsProvider } from '../../context/SettingsContext';
import RedirectSettings from '../RedirectSettings';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

const renderScreen = () =>
	render(
		<SettingsProvider>
			<RedirectSettings />
		</SettingsProvider>
	);

const loginField = () => screen.getByLabelText( 'Login Redirect URL' );
const logoutField = () => screen.getByLabelText( 'Logout Redirect URL' );
const saveButton = () =>
	screen.getByRole( 'button', { name: /save changes/i } );

describe( 'RedirectSettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'hydrates the fields from the fetched settings', async () => {
		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/welcome/',
			wplalr_logout_redirect: 'https://example.test/bye/',
		} );

		renderScreen();

		await waitFor( () =>
			expect( loginField() ).toHaveValue(
				'https://example.test/welcome/'
			)
		);
		expect( logoutField() ).toHaveValue( 'https://example.test/bye/' );
	} );

	it( 'builds the field placeholders from the localized home URL', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderScreen();

		expect( loginField() ).toHaveAttribute(
			'placeholder',
			'https://example.test/example-login-redirect-link/'
		);
		expect( logoutField() ).toHaveAttribute(
			'placeholder',
			'https://example.test/example-logout-redirect-link/'
		);

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
	} );

	it( 'submits the edited URLs to the settings endpoint', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/old-login/',
			wplalr_logout_redirect: 'https://example.test/old-logout/',
		} );

		renderScreen();

		await waitFor( () =>
			expect( loginField() ).toHaveValue(
				'https://example.test/old-login/'
			)
		);

		apiFetch.mockResolvedValueOnce( {
			wplalr_login_redirect: 'https://example.test/new-login/',
			wplalr_logout_redirect: 'https://example.test/old-logout/',
		} );

		await user.clear( loginField() );
		await user.type( loginField(), 'https://example.test/new-login/' );
		await user.click( saveButton() );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenLastCalledWith( {
				path: '/wplalr/v1/settings',
				method: 'POST',
				data: {
					wplalr_login_redirect: 'https://example.test/new-login/',
					wplalr_logout_redirect: 'https://example.test/old-logout/',
				},
			} )
		);
	} );

	it( 'disables the save button while saving', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValueOnce( {} );

		renderScreen();

		await waitFor( () => expect( saveButton() ).toBeEnabled() );

		let resolveSave;
		apiFetch.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveSave = resolve;
				} )
		);

		await user.click( saveButton() );

		expect( saveButton() ).toBeDisabled();

		await act( async () => {
			resolveSave( {} );
		} );

		expect( saveButton() ).toBeEnabled();
	} );

	it( 'renders the placeholder hint chips', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderScreen();

		expect(
			screen.getByRole( 'button', { name: 'Copy {{username}}' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Copy {{user_slug}}' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Copy {{website_url}}' } )
		).toBeInTheDocument();

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
	} );
} );

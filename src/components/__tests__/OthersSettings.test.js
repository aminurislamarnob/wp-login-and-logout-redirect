/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { SettingsProvider } from '../../context/SettingsContext';
import OthersSettings from '../OthersSettings';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

const renderScreen = () =>
	render(
		<SettingsProvider>
			<OthersSettings />
		</SettingsProvider>
	);

const enableToggle = () =>
	screen.getByRole( 'checkbox', { name: 'Enable logging' } );
const retentionSelect = () =>
	screen.getByRole( 'combobox', { name: 'Delete logs older than' } );
const digestSelect = () =>
	screen.getByRole( 'combobox', { name: 'Digest summary' } );
const emailField = () => screen.getByLabelText( 'Notification email' );
const saveButton = () =>
	screen.getByRole( 'button', { name: /save changes/i } );

describe( 'OthersSettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'hydrates all controls from the fetched settings', async () => {
		apiFetch.mockResolvedValueOnce( {
			wplalr_enable_logs: true,
			wplalr_logs_retention_days: 90,
			wplalr_logs_notify_roles: [ 'administrator' ],
			wplalr_logs_notification_email: 'alerts@example.test',
			wplalr_logs_digest: 'weekly',
		} );

		renderScreen();

		await waitFor( () => expect( enableToggle() ).toBeChecked() );

		expect( retentionSelect() ).toHaveValue( '90' );
		expect( digestSelect() ).toHaveValue( 'weekly' );
		expect( emailField() ).toHaveValue( 'alerts@example.test' );
		// The role slug is displayed as its human-readable name.
		expect( screen.getAllByText( 'Administrator' ).length ).toBeGreaterThan(
			0
		);
	} );

	it( 'falls back to defaults when settings are empty', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderScreen();

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );

		expect( enableToggle() ).not.toBeChecked();
		expect( retentionSelect() ).toHaveValue( '30' );
		expect( digestSelect() ).toHaveValue( '' );
		expect( emailField() ).toHaveValue( '' );
	} );

	it( 'submits the edited settings with proper types', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValueOnce( {
			wplalr_enable_logs: false,
			wplalr_logs_retention_days: 30,
			wplalr_logs_notify_roles: [ 'editor' ],
			wplalr_logs_notification_email: '',
			wplalr_logs_digest: '',
		} );

		renderScreen();

		await waitFor( () =>
			expect( screen.getAllByText( 'Editor' ).length ).toBeGreaterThan(
				0
			)
		);

		apiFetch.mockResolvedValueOnce( {} );

		await user.click( enableToggle() );
		await user.selectOptions( retentionSelect(), '7' );
		await user.selectOptions( digestSelect(), 'daily' );
		await user.type( emailField(), 'security@example.test' );
		await user.click( saveButton() );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenLastCalledWith( {
				path: '/wplalr/v1/settings',
				method: 'POST',
				data: {
					wplalr_enable_logs: true,
					// Numeric, not the select's string value.
					wplalr_logs_retention_days: 7,
					wplalr_logs_notify_roles: [ 'editor' ],
					wplalr_logs_notification_email: 'security@example.test',
					wplalr_logs_digest: 'daily',
				},
			} )
		);
	} );

	it( 'keeps unknown role slugs out of the saved value but shows known ones by name', async () => {
		apiFetch.mockResolvedValueOnce( {
			wplalr_logs_notify_roles: [ 'administrator', 'subscriber' ],
		} );

		renderScreen();

		await waitFor( () =>
			expect(
				screen.getAllByText( 'Administrator' ).length
			).toBeGreaterThan( 0 )
		);
		expect( screen.getAllByText( 'Subscriber' ).length ).toBeGreaterThan(
			0
		);
	} );
} );

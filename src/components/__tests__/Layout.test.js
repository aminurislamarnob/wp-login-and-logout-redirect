/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addFilter, removeFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { SettingsProvider } from '../../context/SettingsContext';
import Layout from '../Layout';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

const renderLayout = ( initialPath = '/' ) =>
	render(
		<SettingsProvider>
			{ /* The future flags silence React Router's v7 upgrade warnings,
			     which @wordpress/jest-console would otherwise fail on. */ }
			<MemoryRouter
				initialEntries={ [ initialPath ] }
				future={ {
					v7_startTransition: true,
					v7_relativeSplatPath: true,
				} }
			>
				<Routes>
					<Route path="/" element={ <Layout /> }>
						<Route index element={ <div>redirects view</div> } />
						<Route path="rules" element={ <div>rules view</div> } />
						<Route
							path="others"
							element={ <div>others view</div> }
						/>
					</Route>
				</Routes>
			</MemoryRouter>
		</SettingsProvider>
	);

describe( 'Layout', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'shows the loading skeleton while settings are being fetched', () => {
		// A fetch that never settles keeps isLoading true.
		apiFetch.mockImplementationOnce( () => new Promise( () => {} ) );

		const { container } = renderLayout();

		expect(
			container.querySelectorAll( '.wplalr-skeleton-tab' )
		).toHaveLength( 2 );
		expect( screen.queryByRole( 'link' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'redirects view' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the tabs and the active sub-view once loaded', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderLayout();

		expect(
			await screen.findByRole( 'link', { name: 'Redirects' } )
		).toHaveClass( 'is-active' );
		expect( screen.getByRole( 'link', { name: 'Rules' } ) ).not.toHaveClass(
			'is-active'
		);
		expect(
			screen.getByRole( 'link', { name: 'Others' } )
		).not.toHaveClass( 'is-active' );
		expect( screen.getByText( 'redirects view' ) ).toBeInTheDocument();
	} );

	it( 'switches sub-view and active tab on navigation', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValueOnce( {} );

		renderLayout();

		await user.click(
			await screen.findByRole( 'link', { name: 'Rules' } )
		);

		expect( screen.getByText( 'rules view' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'link', { name: 'Rules' } ) ).toHaveClass(
			'is-active'
		);
		expect(
			screen.getByRole( 'link', { name: 'Redirects' } )
		).not.toHaveClass( 'is-active' );
	} );

	it( 'lets extensions add tabs via the wplalr_tabs filter', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		addFilter( 'wplalr_tabs', 'wplalr-test/pro-tab', ( tabs ) => [
			...tabs,
			{ to: '/pro', icon: () => null, label: 'Pro' },
		] );

		try {
			renderLayout();

			expect(
				await screen.findByRole( 'link', { name: 'Pro' } )
			).toBeInTheDocument();
		} finally {
			removeFilter( 'wplalr_tabs', 'wplalr-test/pro-tab' );
		}
	} );

	it( 'marks the current tab active when starting on a sub-route', async () => {
		apiFetch.mockResolvedValueOnce( {} );

		renderLayout( '/others' );

		expect(
			await screen.findByRole( 'link', { name: 'Others' } )
		).toHaveClass( 'is-active' );
		expect( screen.getByText( 'others view' ) ).toBeInTheDocument();
	} );
} );

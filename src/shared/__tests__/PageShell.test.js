/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { addFilter, removeFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import PageShell from '../PageShell';

describe( 'PageShell', () => {
	afterEach( () => {
		act( () => {
			dispatch( noticesStore ).removeAllNotices();
		} );
	} );

	it( 'renders a nav link for every admin page with the active one marked', () => {
		render(
			<PageShell current="wplalr_audit_logs">
				<div>view body</div>
			</PageShell>
		);

		const active = screen.getByRole( 'link', { name: 'Audit Logs' } );
		expect( active ).toHaveClass( 'is-active' );
		expect( active ).toHaveAttribute( 'aria-current', 'page' );
		expect( active ).toHaveAttribute(
			'href',
			'https://example.test/wp-admin/admin.php?page=wplalr_audit_logs'
		);

		for ( const label of [
			'Redirect Options',
			'Logged-in Users',
			"What's New",
		] ) {
			const link = screen.getByRole( 'link', { name: label } );
			expect( link ).not.toHaveClass( 'is-active' );
			expect( link ).not.toHaveAttribute( 'aria-current' );
		}

		expect( screen.getByText( 'view body' ) ).toBeInTheDocument();
	} );

	it( 'renders the default header actions', () => {
		render(
			<PageShell current="wplalr_login_logout_redirect">
				<div />
			</PageShell>
		);

		expect(
			screen.getByRole( 'link', { name: 'Documentation' } )
		).toHaveAttribute(
			'href',
			'https://wordpress.org/plugins/wp-login-and-logout-redirect/'
		);
		expect(
			screen.getByRole( 'link', { name: 'Support Me' } )
		).toHaveAttribute( 'href', 'https://buymeacoffee.com/aiarnob' );
	} );

	it( 'lets extensions replace the header actions via wplalr_header_actions', () => {
		addFilter( 'wplalr_header_actions', 'wplalr-test/actions', () => (
			<button>Pro Action</button>
		) );

		try {
			render(
				<PageShell current="wplalr_login_logout_redirect">
					<div />
				</PageShell>
			);

			expect(
				screen.getByRole( 'button', { name: 'Pro Action' } )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'link', { name: 'Documentation' } )
			).not.toBeInTheDocument();
		} finally {
			removeFilter( 'wplalr_header_actions', 'wplalr-test/actions' );
		}
	} );

	it( 'shows snackbar notices from the notices store and hides other types', () => {
		render(
			<PageShell current="wplalr_login_logout_redirect">
				<div />
			</PageShell>
		);

		act( () => {
			dispatch( noticesStore ).createSuccessNotice( 'Saved fine!', {
				type: 'snackbar',
			} );
			dispatch( noticesStore ).createErrorNotice( 'A default notice', {
				type: 'default',
			} );
		} );

		// The message renders in the snackbar and again in the a11y
		// announcement live region, so match within the snackbar itself.
		const snackbar = screen.getByTestId( 'snackbar' );
		expect( snackbar ).toHaveTextContent( 'Saved fine!' );
		expect(
			screen.queryByText( 'A default notice' )
		).not.toBeInTheDocument();
	} );
} );

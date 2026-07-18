/**
 * External dependencies
 */
import { render, screen, fireEvent, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PlaceholderHint from '../PlaceholderHint';

const setClipboard = ( value ) =>
	Object.defineProperty( window.navigator, 'clipboard', {
		value,
		configurable: true,
	} );

describe( 'PlaceholderHint', () => {
	afterEach( () => {
		setClipboard( undefined );
		jest.useRealTimers();
	} );

	it( 'renders a copy chip for each placeholder', () => {
		render( <PlaceholderHint /> );

		expect( screen.getByText( '{{username}}' ) ).toBeInTheDocument();
		expect( screen.getByText( '{{user_slug}}' ) ).toBeInTheDocument();
		expect( screen.getByText( '{{website_url}}' ) ).toBeInTheDocument();
	} );

	it( 'copies the placeholder via the Clipboard API and shows feedback', async () => {
		const writeText = jest.fn().mockResolvedValue();
		setClipboard( { writeText } );
		jest.useFakeTimers();

		render( <PlaceholderHint /> );

		const chip = screen.getByRole( 'button', {
			name: 'Copy {{username}}',
		} );

		fireEvent.click( chip );
		// Flush the async copy before checking the feedback state.
		await act( async () => {} );

		expect( writeText ).toHaveBeenCalledWith( '{{username}}' );
		expect( chip ).toHaveTextContent( 'Copied!' );

		act( () => {
			jest.advanceTimersByTime( 1500 );
		} );

		expect( chip ).toHaveTextContent( '{{username}}' );
	} );

	it( 'falls back to execCommand when the Clipboard API is unavailable', async () => {
		setClipboard( undefined );
		document.execCommand = jest.fn( () => true );

		render( <PlaceholderHint /> );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Copy {{website_url}}' } )
		);
		await act( async () => {} );

		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( screen.getByText( 'Copied!' ) ).toBeInTheDocument();

		delete document.execCommand;
	} );
} );

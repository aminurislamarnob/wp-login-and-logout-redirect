import { __, sprintf } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';

const PLACEHOLDERS = [ '{{username}}', '{{user_slug}}', '{{website_url}}' ];

/**
 * Copy text to the clipboard, falling back to a hidden textarea when the
 * async Clipboard API is unavailable (e.g. non-secure origins).
 *
 * @param {string} text Text to copy.
 * @return {Promise<void>} Resolves once the text is copied.
 */
const copyText = async ( text ) => {
	if ( navigator.clipboard?.writeText ) {
		await navigator.clipboard.writeText( text );
		return;
	}

	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild( textarea );
	textarea.select();
	document.execCommand( 'copy' );
	document.body.removeChild( textarea );
};

/**
 * The "Placeholders:" hint rendered as one-click copy chips.
 */
const PlaceholderHint = () => {
	const [ copied, setCopied ] = useState( null );
	const timeoutRef = useRef( null );

	useEffect( () => () => clearTimeout( timeoutRef.current ), [] );

	const onCopy = async ( placeholder ) => {
		try {
			await copyText( placeholder );
		} catch {
			return;
		}

		setCopied( placeholder );
		clearTimeout( timeoutRef.current );
		timeoutRef.current = setTimeout( () => setCopied( null ), 1500 );
	};

	return (
		<div className="wplalr-placeholder-hint">
			<span className="wplalr-placeholder-hint-label">
				{ __( 'Placeholders:', 'wp-login-logout-redirect' ) }
			</span>
			{ PLACEHOLDERS.map( ( placeholder ) => (
				<button
					key={ placeholder }
					type="button"
					className={ `wplalr-placeholder-chip${
						copied === placeholder ? ' is-copied' : ''
					}` }
					onClick={ () => onCopy( placeholder ) }
					aria-label={ sprintf(
						/* translators: %s: placeholder token, e.g. {{username}}. */
						__( 'Copy %s', 'wp-login-logout-redirect' ),
						placeholder
					) }
					title={ __( 'Click to copy', 'wp-login-logout-redirect' ) }
				>
					{ copied === placeholder
						? __( 'Copied!', 'wp-login-logout-redirect' )
						: placeholder }
				</button>
			) ) }
		</div>
	);
};

export default PlaceholderHint;

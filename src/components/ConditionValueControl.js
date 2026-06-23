import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { applyFilters } from '@wordpress/hooks';

const ROLES = window.wplalrAdmin?.roles ?? {};

const formatUserLabel = ( name, id ) => `${ name } (#${ id })`;
const parseUserId = ( label ) => {
	const match = /\(#(\d+)\)\s*$/.exec( label );
	return match ? match[ 1 ] : '';
};

/**
 * Renders the value selector for a single condition, switching on its type.
 *
 * - role: token field backed by the site's editable roles.
 * - capability: free-text token field.
 * - user: token field with debounced async user search.
 *
 * @param {Object}   props
 * @param {string}   props.type     Condition type: role|user|capability.
 * @param {string[]} props.values   Stored values (role slugs, user IDs, caps).
 * @param {Function} props.onChange Receives the next values array.
 */
const ConditionValueControl = ( { type, values, onChange } ) => {
	// Pro extensions render value controls for their custom match types.
	const custom = applyFilters( 'wplalr_condition_value_control', null, {
		type,
		values,
		onChange,
	} );

	if ( custom ) {
		return custom;
	}

	if ( type === 'role' ) {
		const nameBySlug = ROLES;
		const slugByName = Object.fromEntries(
			Object.entries( ROLES ).map( ( [ slug, name ] ) => [ name, slug ] )
		);

		return (
			<FormTokenField
				label={ __( 'Roles', 'wp-login-logout-redirect' ) }
				value={ values.map( ( slug ) => nameBySlug[ slug ] ?? slug ) }
				suggestions={ Object.values( nameBySlug ) }
				onChange={ ( tokens ) =>
					onChange(
						tokens
							.map( ( token ) => slugByName[ token ] ?? token )
							.filter( ( slug ) => nameBySlug[ slug ] )
					)
				}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				__experimentalExpandOnFocus
			/>
		);
	}

	if ( type === 'capability' ) {
		return (
			<FormTokenField
				label={ __( 'Capabilities', 'wp-login-logout-redirect' ) }
				value={ values }
				onChange={ onChange }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		);
	}

	return <UserValueControl values={ values } onChange={ onChange } />;
};

/**
 * User token field with debounced search and label caching.
 *
 * @param {Object}   props
 * @param {string[]} props.values   Stored user IDs (as strings).
 * @param {Function} props.onChange Receives the next array of user IDs.
 */
const UserValueControl = ( { values, onChange } ) => {
	const [ labelById, setLabelById ] = useState( {} );
	const [ suggestions, setSuggestions ] = useState( [] );
	const debounceRef = useRef();

	// Resolve labels for already-stored IDs that we have not seen yet.
	useEffect( () => {
		const missing = values.filter( ( id ) => ! labelById[ id ] );

		if ( missing.length === 0 ) {
			return;
		}

		apiFetch( {
			path: addQueryArgs( '/wp/v2/users', {
				include: missing,
				per_page: 100,
				context: 'edit',
				_fields: 'id,name',
			} ),
		} )
			.then( ( users ) => {
				setLabelById( ( prev ) => {
					const next = { ...prev };
					users.forEach( ( user ) => {
						next[ user.id ] = formatUserLabel( user.name, user.id );
					} );
					return next;
				} );
			} )
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ values ] );

	const search = useCallback( ( text ) => {
		window.clearTimeout( debounceRef.current );

		if ( ! text ) {
			setSuggestions( [] );
			return;
		}

		debounceRef.current = window.setTimeout( () => {
			apiFetch( {
				path: addQueryArgs( '/wp/v2/users', {
					search: text,
					per_page: 20,
					context: 'edit',
					_fields: 'id,name',
				} ),
			} )
				.then( ( users ) => {
					setLabelById( ( prev ) => {
						const next = { ...prev };
						users.forEach( ( user ) => {
							next[ user.id ] = formatUserLabel(
								user.name,
								user.id
							);
						} );
						return next;
					} );
					setSuggestions(
						users.map( ( user ) =>
							formatUserLabel( user.name, user.id )
						)
					);
				} )
				.catch( () => setSuggestions( [] ) );
		}, 300 );
	}, [] );

	return (
		<FormTokenField
			label={ __( 'Users', 'wp-login-logout-redirect' ) }
			value={ values.map(
				( id ) => labelById[ id ] ?? formatUserLabel( '—', id )
			) }
			suggestions={ suggestions }
			onInputChange={ search }
			onChange={ ( tokens ) =>
				onChange(
					tokens
						.map( ( token ) => parseUserId( token ) )
						.filter( Boolean )
				)
			}
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
};

export default ConditionValueControl;

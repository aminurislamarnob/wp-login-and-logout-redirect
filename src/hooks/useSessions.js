/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { addQueryArgs } from '@wordpress/url';

const SESSIONS_PATH = '/wplalr/v1/sessions';

/**
 * Data hook for the Logged-in Users view.
 *
 * Lists users with active sessions and exposes the destroy mutations (single
 * session, whole user, bulk, everyone). Re-fetches after each action.
 *
 * @return {Object} Sessions state and actions.
 */
export const useSessions = () => {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );

	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ role, setRole ] = useState( '' );

	const perPage = 20;

	const notifyError = useCallback(
		( err ) =>
			createErrorNotice(
				err?.message ??
					__( 'Something went wrong.', 'wp-login-logout-redirect' ),
				{ type: 'snackbar', id: 'wplalr-sessions-error' }
			),
		[ createErrorNotice ]
	);

	const fetchSessions = useCallback( async () => {
		setIsLoading( true );

		try {
			const path = addQueryArgs( SESSIONS_PATH, {
				page,
				per_page: perPage,
				search,
				role,
			} );

			const response = await apiFetch( { path, parse: false } );
			const data = await response.json();

			setItems( Array.isArray( data ) ? data : [] );
			setTotal(
				parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 )
			);
			setTotalPages(
				parseInt( response.headers.get( 'X-WP-TotalPages' ) || '0', 10 )
			);
		} catch ( err ) {
			notifyError( err );
		} finally {
			setIsLoading( false );
		}
	}, [ page, search, role, notifyError ] );

	useEffect( () => {
		fetchSessions();
	}, [ fetchSessions ] );

	const refresh = useCallback( () => fetchSessions(), [ fetchSessions ] );

	const withResult = useCallback(
		async ( request, successMessage ) => {
			try {
				await request();
				if ( successMessage ) {
					createSuccessNotice( successMessage, {
						type: 'snackbar',
						id: 'wplalr-sessions-action',
					} );
				}
				fetchSessions();
			} catch ( err ) {
				notifyError( err );
			}
		},
		[ createSuccessNotice, fetchSessions, notifyError ]
	);

	const destroySession = useCallback(
		( userId, tokenId ) =>
			withResult(
				() =>
					apiFetch( {
						path: `${ SESSIONS_PATH }/${ userId }/${ tokenId }`,
						method: 'DELETE',
					} ),
				__( 'Session ended.', 'wp-login-logout-redirect' )
			),
		[ withResult ]
	);

	const destroyUser = useCallback(
		( userId ) =>
			withResult(
				() =>
					apiFetch( {
						path: `${ SESSIONS_PATH }/${ userId }`,
						method: 'DELETE',
					} ),
				__( 'User logged out.', 'wp-login-logout-redirect' )
			),
		[ withResult ]
	);

	const destroyBulk = useCallback(
		( userIds, excludeSelf = true ) =>
			withResult(
				() =>
					apiFetch( {
						path: `${ SESSIONS_PATH }/bulk-destroy`,
						method: 'POST',
						data: {
							user_ids: userIds,
							exclude_self: excludeSelf,
						},
					} ),
				__( 'Selected users logged out.', 'wp-login-logout-redirect' )
			),
		[ withResult ]
	);

	const destroyAll = useCallback(
		( excludeSelf = true ) =>
			withResult(
				() =>
					apiFetch( {
						path: `${ SESSIONS_PATH }/destroy-all`,
						method: 'POST',
						data: { exclude_self: excludeSelf },
					} ),
				__( 'Everyone logged out.', 'wp-login-logout-redirect' )
			),
		[ withResult ]
	);

	const updateSearch = useCallback( ( value ) => {
		setSearch( value );
		setPage( 1 );
	}, [] );

	const updateRole = useCallback( ( value ) => {
		setRole( value );
		setPage( 1 );
	}, [] );

	return {
		items,
		total,
		totalPages,
		isLoading,
		page,
		search,
		role,
		setPage,
		setSearch: updateSearch,
		setRole: updateRole,
		refresh,
		destroySession,
		destroyUser,
		destroyBulk,
		destroyAll,
	};
};

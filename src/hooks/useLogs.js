/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { addQueryArgs } from '@wordpress/url';

const LOGS_PATH = '/wplalr/v1/logs';
const STATS_PATH = '/wplalr/v1/logs/stats';
const SETTINGS_PATH = '/wplalr/v1/settings';

const EMPTY_STATS = { login: 0, logout: 0, failed: 0, total: 0 };

/**
 * Data hook for the Audit Logs view.
 *
 * Owns the paginated/filtered log list, the stat cards, the enable/retention
 * settings (folded into /settings), and the delete mutations. Re-fetches the
 * list + stats after each mutation.
 *
 * @return {Object} Logs state and actions.
 */
export const useLogs = () => {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ stats, setStats ] = useState( EMPTY_STATS );

	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );

	const [ enabled, setEnabled ] = useState( false );
	const [ retentionDays, setRetentionDays ] = useState( 30 );

	// Query state.
	const [ page, setPage ] = useState( 1 );
	const [ event, setEvent ] = useState( '' );
	const [ search, setSearch ] = useState( '' );

	const perPage = 20;

	const notifyError = useCallback(
		( err ) =>
			createErrorNotice(
				err?.message ??
					__( 'Something went wrong.', 'wp-login-logout-redirect' ),
				{ type: 'snackbar', id: 'wplalr-logs-error' }
			),
		[ createErrorNotice ]
	);

	const fetchStats = useCallback( async () => {
		try {
			const response = await apiFetch( { path: STATS_PATH } );
			setStats( { ...EMPTY_STATS, ...( response ?? {} ) } );
		} catch ( err ) {
			notifyError( err );
		}
	}, [ notifyError ] );

	const fetchLogs = useCallback( async () => {
		setIsLoading( true );

		try {
			const path = addQueryArgs( LOGS_PATH, {
				page,
				per_page: perPage,
				event,
				search,
			} );

			// parse: false so we can read pagination headers.
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
	}, [ page, event, search, notifyError ] );

	// Seed enable/retention from /settings once on mount.
	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: SETTINGS_PATH } )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				setEnabled( !! response?.wplalr_enable_logs );
				setRetentionDays(
					Number( response?.wplalr_logs_retention_days ?? 30 )
				);
			} )
			.catch( notifyError );

		return () => {
			cancelled = true;
		};
	}, [ notifyError ] );

	// Re-fetch the list whenever query state changes.
	useEffect( () => {
		fetchLogs();
	}, [ fetchLogs ] );

	// Stats once on mount.
	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

	const refresh = useCallback( () => {
		fetchLogs();
		fetchStats();
	}, [ fetchLogs, fetchStats ] );

	const saveLogSettings = useCallback(
		async ( next ) => {
			setIsSavingSettings( true );

			try {
				const response = await apiFetch( {
					path: SETTINGS_PATH,
					method: 'POST',
					data: next,
				} );

				setEnabled( !! response?.wplalr_enable_logs );
				setRetentionDays(
					Number( response?.wplalr_logs_retention_days ?? 30 )
				);
				createSuccessNotice(
					__( 'Settings saved.', 'wp-login-logout-redirect' ),
					{
						type: 'snackbar',
						id: 'wplalr-logs-settings-saved',
						isDismissible: false,
					}
				);
			} catch ( err ) {
				notifyError( err );
			} finally {
				setIsSavingSettings( false );
			}
		},
		[ createSuccessNotice, notifyError ]
	);

	const deleteLog = useCallback(
		async ( id ) => {
			try {
				await apiFetch( {
					path: `${ LOGS_PATH }/${ id }`,
					method: 'DELETE',
				} );
				refresh();
			} catch ( err ) {
				notifyError( err );
			}
		},
		[ refresh, notifyError ]
	);

	const deleteAllLogs = useCallback( async () => {
		try {
			await apiFetch( { path: LOGS_PATH, method: 'DELETE' } );
			setPage( 1 );
			refresh();
			createSuccessNotice(
				__( 'All logs deleted.', 'wp-login-logout-redirect' ),
				{ type: 'snackbar', id: 'wplalr-logs-cleared' }
			);
		} catch ( err ) {
			notifyError( err );
		}
	}, [ refresh, createSuccessNotice, notifyError ] );

	// Reset to page 1 when a filter changes.
	const updateEvent = useCallback( ( value ) => {
		setEvent( value );
		setPage( 1 );
	}, [] );

	const updateSearch = useCallback( ( value ) => {
		setSearch( value );
		setPage( 1 );
	}, [] );

	return {
		items,
		total,
		totalPages,
		stats,
		isLoading,
		isSavingSettings,
		enabled,
		retentionDays,
		page,
		event,
		search,
		setPage,
		setEvent: updateEvent,
		setSearch: updateSearch,
		refresh,
		saveLogSettings,
		deleteLog,
		deleteAllLogs,
	};
};

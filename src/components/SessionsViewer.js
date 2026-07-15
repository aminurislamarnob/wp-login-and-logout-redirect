/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, Fragment } from '@wordpress/element';
import {
	Card,
	CardBody,
	Button,
	Spinner,
	SelectControl,
	SearchControl,
	CheckboxControl,
	Modal,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { useSessions } from '../hooks/useSessions';

const ROLES = window.wplalrAdmin?.roles || {};
const CURRENT_USER_ID = Number( window.wplalrAdmin?.currentUserId || 0 );

const ROLE_OPTIONS = [
	{ label: __( 'All roles', 'wp-login-logout-redirect' ), value: '' },
	...Object.entries( ROLES ).map( ( [ value, label ] ) => ( {
		label,
		value,
	} ) ),
];

/**
 * Format a unix timestamp as a coarse relative string.
 *
 * @param {number}  ts     Target timestamp (seconds).
 * @param {boolean} future Whether the target is expected in the future.
 * @return {string} Relative label.
 */
const relative = ( ts, future = false ) => {
	if ( ! ts ) {
		return '—';
	}

	const now = Math.floor( Date.now() / 1000 );
	const diff = Math.abs( future ? ts - now : now - ts );

	const mins = Math.round( diff / 60 );
	const hours = Math.round( diff / 3600 );
	const days = Math.round( diff / 86400 );

	let value;
	if ( diff < 3600 ) {
		/* translators: %d: number of minutes. */
		value = sprintf( __( '%d min', 'wp-login-logout-redirect' ), mins );
	} else if ( diff < 86400 ) {
		/* translators: %d: number of hours. */
		value = sprintf( __( '%d hr', 'wp-login-logout-redirect' ), hours );
	} else {
		/* translators: %d: number of days. */
		value = sprintf( __( '%d days', 'wp-login-logout-redirect' ), days );
	}

	return future
		? /* translators: %s: a duration like "5 min". */
		  sprintf( __( 'in %s', 'wp-login-logout-redirect' ), value )
		: /* translators: %s: a duration like "5 min". */
		  sprintf( __( '%s ago', 'wp-login-logout-redirect' ), value );
};

const SessionsViewer = () => {
	const {
		items,
		total,
		totalPages,
		isLoading,
		page,
		search,
		role,
		setPage,
		setSearch,
		setRole,
		refresh,
		destroySession,
		destroyUser,
		destroyBulk,
		destroyAll,
	} = useSessions();

	const [ selected, setSelected ] = useState( [] );
	const [ expanded, setExpanded ] = useState( [] );
	const [ confirm, setConfirm ] = useState( null );
	const [ excludeSelf, setExcludeSelf ] = useState( true );

	// Pro extensions add columns / row actions via these filters.
	const extraColumns = applyFilters( 'wplalr_session_columns', null );

	const toggleSelect = ( userId ) =>
		setSelected( ( prev ) =>
			prev.includes( userId )
				? prev.filter( ( id ) => id !== userId )
				: [ ...prev, userId ]
		);

	const toggleExpand = ( userId ) =>
		setExpanded( ( prev ) =>
			prev.includes( userId )
				? prev.filter( ( id ) => id !== userId )
				: [ ...prev, userId ]
		);

	const openConfirm = ( config ) => {
		setExcludeSelf( true );
		setConfirm( config );
	};

	const runConfirm = () => {
		if ( confirm?.action ) {
			confirm.action();
		}
		setConfirm( null );
	};

	const onLogoutEveryone = () =>
		openConfirm( {
			title: __( 'Force logout everyone?', 'wp-login-logout-redirect' ),
			message: __(
				'This ends the active sessions of all logged-in users.',
				'wp-login-logout-redirect'
			),
			withExcludeSelf: true,
			action: () => destroyAll( excludeSelf ),
		} );

	const onLogoutSelected = () =>
		openConfirm( {
			title: __(
				'Force logout selected users?',
				'wp-login-logout-redirect'
			),
			message: sprintf(
				/* translators: %d: number of selected users. */
				__(
					'This ends the sessions of %d selected user(s).',
					'wp-login-logout-redirect'
				),
				selected.length
			),
			withExcludeSelf: true,
			action: () => {
				destroyBulk( selected, excludeSelf );
				setSelected( [] );
			},
		} );

	const onLogoutUser = ( row ) => {
		const isSelf = row.user_id === CURRENT_USER_ID;
		openConfirm( {
			title: __( 'Log out this user?', 'wp-login-logout-redirect' ),
			message: isSelf
				? __(
						'This is your own account — you will be logged out immediately.',
						'wp-login-logout-redirect'
				  )
				: sprintf(
						/* translators: %s: user display name. */
						__(
							'This ends all active sessions for %s.',
							'wp-login-logout-redirect'
						),
						row.display_name || row.user_login
				  ),
			action: () => destroyUser( row.user_id ),
		} );
	};

	const allOnPageSelected =
		items.length > 0 &&
		items.every( ( r ) => selected.includes( r.user_id ) );

	const toggleSelectAll = () =>
		setSelected( allOnPageSelected ? [] : items.map( ( r ) => r.user_id ) );

	return (
		<>
			{ /* Toolbar */ }
			<div className="wplalr-section">
				<Flex className="wplalr-logs-toolbar" wrap>
					<FlexItem isBlock>
						<SearchControl
							__nextHasNoMarginBottom
							label={ __(
								'Search users',
								'wp-login-logout-redirect'
							) }
							placeholder={ __(
								'Search name or email…',
								'wp-login-logout-redirect'
							) }
							value={ search }
							onChange={ setSearch }
						/>
					</FlexItem>
					<FlexItem>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Role', 'wp-login-logout-redirect' ) }
							hideLabelFromVision
							value={ role }
							options={ ROLE_OPTIONS }
							onChange={ setRole }
						/>
					</FlexItem>
					<FlexItem>
						<Button variant="secondary" onClick={ refresh }>
							{ __( 'Refresh', 'wp-login-logout-redirect' ) }
						</Button>
					</FlexItem>
					<FlexItem>
						<Button
							variant="secondary"
							isDestructive
							onClick={ onLogoutEveryone }
							disabled={ total === 0 }
						>
							{ __(
								'Force logout everyone',
								'wp-login-logout-redirect'
							) }
						</Button>
					</FlexItem>
				</Flex>
			</div>

			{ /* Bulk bar */ }
			{ selected.length > 0 && (
				<div className="wplalr-section">
					<Flex className="wplalr-bulk-bar">
						<FlexItem>
							{ sprintf(
								/* translators: %d: number of selected users. */
								__( '%d selected', 'wp-login-logout-redirect' ),
								selected.length
							) }
						</FlexItem>
						<FlexItem>
							<Button
								variant="secondary"
								isDestructive
								onClick={ onLogoutSelected }
							>
								{ __(
									'Force logout selected',
									'wp-login-logout-redirect'
								) }
							</Button>
						</FlexItem>
					</Flex>
				</div>
			) }

			{ /* Table */ }
			<div className="wplalr-section">
				<Card>
					<CardBody className="wplalr-form-section-body">
						{ isLoading && (
							<div className="wplalr-loading">
								<Spinner />
							</div>
						) }
						{ ! isLoading && items.length === 0 && (
							<p className="wplalr-section-description">
								{ __(
									'No users currently have active sessions.',
									'wp-login-logout-redirect'
								) }
							</p>
						) }
						{ ! isLoading && items.length > 0 && (
							<table className="wplalr-logs-table wplalr-sessions-table">
								<thead>
									<tr>
										<th className="wplalr-col-check">
											<CheckboxControl
												__nextHasNoMarginBottom
												checked={ allOnPageSelected }
												onChange={ toggleSelectAll }
												label=""
											/>
										</th>
										<th>
											{ __(
												'User',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Role',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Logged in',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Expires',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Sessions',
												'wp-login-logout-redirect'
											) }
										</th>
										<th
											aria-label={ __(
												'Actions',
												'wp-login-logout-redirect'
											) }
										/>
									</tr>
								</thead>
								<tbody>
									{ items.map( ( row ) => {
										const isSelf =
											row.user_id === CURRENT_USER_ID;
										const first = row.sessions[ 0 ] || {};
										const isExpanded = expanded.includes(
											row.user_id
										);

										return (
											<Fragment key={ row.user_id }>
												<tr>
													<td className="wplalr-col-check">
														<CheckboxControl
															__nextHasNoMarginBottom
															checked={ selected.includes(
																row.user_id
															) }
															onChange={ () =>
																toggleSelect(
																	row.user_id
																)
															}
															label=""
														/>
													</td>
													<td>
														<div className="wplalr-session-user">
															{ row.avatar && (
																<img
																	src={
																		row.avatar
																	}
																	alt=""
																	className="wplalr-session-avatar"
																/>
															) }
															<div>
																<strong>
																	{ row.display_name ||
																		row.user_login }
																	{ isSelf && (
																		<span className="wplalr-you-badge">
																			{ __(
																				'You',
																				'wp-login-logout-redirect'
																			) }
																		</span>
																	) }
																</strong>
																<span className="wplalr-session-email">
																	{
																		row.user_email
																	}
																</span>
															</div>
														</div>
													</td>
													<td>
														{ row.roles
															.map(
																( r ) =>
																	ROLES[
																		r
																	] || r
															)
															.join( ', ' ) }
													</td>
													<td>
														{ relative(
															first.login
														) }
													</td>
													<td>
														{ relative(
															first.expiration,
															true
														) }
													</td>
													<td>
														{ row.session_count }
														{ row.session_count >
															1 && (
															<Button
																variant="link"
																className="wplalr-expand-sessions"
																onClick={ () =>
																	toggleExpand(
																		row.user_id
																	)
																}
															>
																{ isExpanded
																	? __(
																			'Hide',
																			'wp-login-logout-redirect'
																	  )
																	: __(
																			'Show',
																			'wp-login-logout-redirect'
																	  ) }
															</Button>
														) }
													</td>
													<td>
														<Button
															size="small"
															variant="secondary"
															isDestructive
															onClick={ () =>
																onLogoutUser(
																	row
																)
															}
														>
															{ __(
																'Log out',
																'wp-login-logout-redirect'
															) }
														</Button>
													</td>
												</tr>
												{ isExpanded &&
													row.sessions.map(
														( session ) => (
															<tr
																key={
																	session.token_id
																}
																className="wplalr-session-detail"
															>
																<td />
																<td
																	colSpan={
																		4
																	}
																>
																	<span className="wplalr-session-device">
																		{
																			session.browser
																		}{ ' ' }
																		/{ ' ' }
																		{
																			session.device_os
																		}
																		{ session.is_current && (
																			<span className="wplalr-you-badge">
																				{ __(
																					'This device',
																					'wp-login-logout-redirect'
																				) }
																			</span>
																		) }
																	</span>
																	<span className="wplalr-session-ip">
																		{
																			session.ip
																		}
																	</span>
																</td>
																<td />
																<td>
																	<Button
																		size="small"
																		variant="tertiary"
																		isDestructive
																		onClick={ () =>
																			destroySession(
																				row.user_id,
																				session.token_id
																			)
																		}
																	>
																		{ __(
																			'End',
																			'wp-login-logout-redirect'
																		) }
																	</Button>
																</td>
															</tr>
														)
													) }
											</Fragment>
										);
									} ) }
								</tbody>
							</table>
						) }

						{ extraColumns }

						{ totalPages > 1 && (
							<div className="wplalr-logs-pagination">
								<Button
									variant="secondary"
									disabled={ page <= 1 }
									onClick={ () => setPage( page - 1 ) }
								>
									{ __(
										'Previous',
										'wp-login-logout-redirect'
									) }
								</Button>
								<span className="wplalr-logs-page-info">
									{ __( 'Page', 'wp-login-logout-redirect' ) }{ ' ' }
									{ page } / { totalPages }
								</span>
								<Button
									variant="secondary"
									disabled={ page >= totalPages }
									onClick={ () => setPage( page + 1 ) }
								>
									{ __( 'Next', 'wp-login-logout-redirect' ) }
								</Button>
							</div>
						) }
					</CardBody>
				</Card>
			</div>

			{ confirm && (
				<Modal
					title={ confirm.title }
					onRequestClose={ () => setConfirm( null ) }
				>
					<p>{ confirm.message }</p>
					{ confirm.withExcludeSelf && (
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __(
								'Keep me logged in',
								'wp-login-logout-redirect'
							) }
							checked={ excludeSelf }
							onChange={ setExcludeSelf }
						/>
					) }
					<Flex justify="flex-end" className="wplalr-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setConfirm( null ) }
						>
							{ __( 'Cancel', 'wp-login-logout-redirect' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ runConfirm }
						>
							{ __( 'Force logout', 'wp-login-logout-redirect' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</>
	);
};

export default SessionsViewer;

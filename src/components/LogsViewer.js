/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Card,
	CardBody,
	Button,
	Spinner,
	SelectControl,
	SearchControl,
	Modal,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { useLogs } from '../hooks/useLogs';
import { TrashIcon } from './icons';

const EVENT_OPTIONS = [
	{ label: __( 'All events', 'wp-login-logout-redirect' ), value: '' },
	{ label: __( 'Login', 'wp-login-logout-redirect' ), value: 'login' },
	{ label: __( 'Logout', 'wp-login-logout-redirect' ), value: 'logout' },
	{
		label: __( 'Failed login', 'wp-login-logout-redirect' ),
		value: 'failed',
	},
	{
		label: __( 'Forced logout', 'wp-login-logout-redirect' ),
		value: 'forced_logout',
	},
];

const STAT_CARDS = [
	{ key: 'login', label: __( 'Logins', 'wp-login-logout-redirect' ) },
	{ key: 'logout', label: __( 'Logouts', 'wp-login-logout-redirect' ) },
	{
		key: 'failed',
		label: __( 'Failed logins', 'wp-login-logout-redirect' ),
	},
	{ key: 'total', label: __( 'Total events', 'wp-login-logout-redirect' ) },
];

const EVENT_LABELS = {
	login: __( 'Login', 'wp-login-logout-redirect' ),
	logout: __( 'Logout', 'wp-login-logout-redirect' ),
	failed: __( 'Failed', 'wp-login-logout-redirect' ),
	forced_logout: __( 'Forced logout', 'wp-login-logout-redirect' ),
};

const LogsViewer = () => {
	const {
		items,
		total,
		totalPages,
		stats,
		isLoading,
		enabled,
		page,
		event,
		search,
		setPage,
		setEvent,
		setSearch,
		refresh,
		deleteLog,
		deleteAllLogs,
	} = useLogs();

	const [ confirmClear, setConfirmClear ] = useState( false );

	// Pro extensions add table columns / row actions via these filters.
	const columns = applyFilters( 'wplalr_log_columns', null );

	const onClearAll = () => {
		deleteAllLogs();
		setConfirmClear( false );
	};

	const emptyMessage = enabled
		? __(
				'No log entries match your filters yet.',
				'wp-login-logout-redirect'
		  )
		: __(
				'Logging is off. Enable it on the Others tab under Redirect Options to start recording login activity.',
				'wp-login-logout-redirect'
		  );

	return (
		<>
			{ /* Stat cards */ }
			<div className="wplalr-section">
				<div className="wplalr-stat-cards">
					{ STAT_CARDS.map( ( { key, label } ) => (
						<Card key={ key } className="wplalr-stat-card">
							<CardBody>
								<span className="wplalr-stat-value">
									{ stats[ key ] ?? 0 }
								</span>
								<span className="wplalr-stat-label">
									{ label }
								</span>
							</CardBody>
						</Card>
					) ) }
				</div>
			</div>

			{ /* Toolbar */ }
			<div className="wplalr-section">
				<Flex className="wplalr-logs-toolbar" wrap>
					<FlexItem isBlock>
						<SearchControl
							__nextHasNoMarginBottom
							label={ __(
								'Search logs',
								'wp-login-logout-redirect'
							) }
							placeholder={ __(
								'Search username or IP…',
								'wp-login-logout-redirect'
							) }
							value={ search }
							onChange={ setSearch }
						/>
					</FlexItem>
					<FlexItem>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Event', 'wp-login-logout-redirect' ) }
							hideLabelFromVision
							value={ event }
							options={ EVENT_OPTIONS }
							onChange={ setEvent }
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
							onClick={ () => setConfirmClear( true ) }
							disabled={ total === 0 }
						>
							{ __( 'Delete all', 'wp-login-logout-redirect' ) }
						</Button>
					</FlexItem>
				</Flex>
			</div>

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
								{ emptyMessage }
							</p>
						) }
						{ ! isLoading && items.length > 0 && (
							<table className="wplalr-logs-table">
								<thead>
									<tr>
										<th>
											{ __(
												'Time',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'User',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Event',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'IP',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Browser / OS',
												'wp-login-logout-redirect'
											) }
										</th>
										<th>
											{ __(
												'Redirect',
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
									{ items.map( ( row ) => (
										<tr key={ row.id }>
											<td title={ row.created_at }>
												{ row.time_diff }
											</td>
											<td>{ row.username || '—' }</td>
											<td>
												<span
													className={ `wplalr-event-badge is-${ row.status }` }
												>
													{ EVENT_LABELS[
														row.event
													] || row.event }
												</span>
												{ row.error_code && (
													<span className="wplalr-error-code">
														{ row.error_code }
													</span>
												) }
											</td>
											<td>{ row.ip || '—' }</td>
											<td>
												{ row.browser }
												{ row.device_os
													? ` / ${ row.device_os }`
													: '' }
											</td>
											<td className="wplalr-logs-redirect">
												{ row.redirect_url || '—' }
											</td>
											<td>
												<Button
													size="small"
													isDestructive
													className="wplalr-log-delete"
													icon={ <TrashIcon /> }
													label={ __(
														'Delete entry',
														'wp-login-logout-redirect'
													) }
													onClick={ () =>
														deleteLog( row.id )
													}
												/>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						) }

						{ columns }

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
									{ /* translators: 1: current page, 2: total pages. */ }
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

			{ confirmClear && (
				<Modal
					title={ __(
						'Delete all logs?',
						'wp-login-logout-redirect'
					) }
					onRequestClose={ () => setConfirmClear( false ) }
				>
					<p>
						{ __(
							'This permanently removes every recorded event. This cannot be undone.',
							'wp-login-logout-redirect'
						) }
					</p>
					<Flex justify="flex-end">
						<Button
							variant="tertiary"
							onClick={ () => setConfirmClear( false ) }
						>
							{ __( 'Cancel', 'wp-login-logout-redirect' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ onClearAll }
						>
							{ __( 'Delete all', 'wp-login-logout-redirect' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</>
	);
};

export default LogsViewer;

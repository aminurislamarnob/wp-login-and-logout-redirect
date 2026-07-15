import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	ToggleControl,
	SelectControl,
	TextControl,
	FormTokenField,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';

const RETENTION_OPTIONS = [
	{ label: __( '7 days', 'wp-login-logout-redirect' ), value: '7' },
	{ label: __( '30 days', 'wp-login-logout-redirect' ), value: '30' },
	{ label: __( '90 days', 'wp-login-logout-redirect' ), value: '90' },
	{ label: __( 'Keep forever', 'wp-login-logout-redirect' ), value: '0' },
];

const DIGEST_OPTIONS = [
	{ label: __( 'Off', 'wp-login-logout-redirect' ), value: '' },
	{ label: __( 'Daily', 'wp-login-logout-redirect' ), value: 'daily' },
	{ label: __( 'Weekly', 'wp-login-logout-redirect' ), value: 'weekly' },
	{ label: __( 'Monthly', 'wp-login-logout-redirect' ), value: 'monthly' },
];

const ROLES = window.wplalrAdmin?.roles ?? {};

const OthersSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ enableLogs, setEnableLogs ] = useState( false );
	const [ retentionDays, setRetentionDays ] = useState( '30' );
	const [ notifyRoles, setNotifyRoles ] = useState( [] );
	const [ notificationEmail, setNotificationEmail ] = useState( '' );
	const [ digest, setDigest ] = useState( '' );

	useEffect( () => {
		setEnableLogs( !! settings.wplalr_enable_logs );
		setRetentionDays( String( settings.wplalr_logs_retention_days ?? 30 ) );
		setNotifyRoles(
			Array.isArray( settings.wplalr_logs_notify_roles )
				? settings.wplalr_logs_notify_roles
				: []
		);
		setNotificationEmail( settings.wplalr_logs_notification_email ?? '' );
		setDigest( settings.wplalr_logs_digest ?? '' );
	}, [
		settings.wplalr_enable_logs,
		settings.wplalr_logs_retention_days,
		settings.wplalr_logs_notify_roles,
		settings.wplalr_logs_notification_email,
		settings.wplalr_logs_digest,
	] );

	const nameBySlug = ROLES;
	const slugByName = Object.fromEntries(
		Object.entries( ROLES ).map( ( [ slug, name ] ) => [ name, slug ] )
	);

	const handleSubmit = ( event ) => {
		event.preventDefault();
		saveSettings( {
			wplalr_enable_logs: enableLogs,
			wplalr_logs_retention_days: parseInt( retentionDays, 10 ),
			wplalr_logs_notify_roles: notifyRoles,
			wplalr_logs_notification_email: notificationEmail,
			wplalr_logs_digest: digest,
		} );
	};

	return (
		<div
			className="wplalr-section wplalr-section--narrow"
			id="wplalr-others-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="wplalr-form-header-card">
					<CardBody className="wplalr-form-section-header">
						<h3 className="wplalr-section-title">
							{ __(
								'Audit Logging',
								'wp-login-logout-redirect'
							) }
						</h3>
						<p className="wplalr-section-description">
							{ __(
								'Record login, logout and failed-login events. Recorded events appear on the Audit Logs page.',
								'wp-login-logout-redirect'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="wplalr-form-section-body">
						<div className="wplalr-settings-group">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Enable logging',
									'wp-login-logout-redirect'
								) }
								help={ __(
									'When enabled, IP address and browser are stored alongside each event.',
									'wp-login-logout-redirect'
								) }
								checked={ enableLogs }
								onChange={ setEnableLogs }
							/>
						</div>
						<div className="wplalr-settings-group">
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Delete logs older than',
									'wp-login-logout-redirect'
								) }
								value={ retentionDays }
								options={ RETENTION_OPTIONS }
								onChange={ setRetentionDays }
							/>
						</div>
					</CardBody>
				</Card>

				<Card className="wplalr-form-header-card">
					<CardBody className="wplalr-form-section-header">
						<h3 className="wplalr-section-title">
							{ __(
								'Notifications',
								'wp-login-logout-redirect'
							) }
						</h3>
						<p className="wplalr-section-description">
							{ __(
								'Email alerts and digests of login activity. Both require logging to be enabled above.',
								'wp-login-logout-redirect'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="wplalr-form-section-body">
						<div className="wplalr-settings-group">
							<FormTokenField
								label={ __(
									'Alert on login for roles',
									'wp-login-logout-redirect'
								) }
								value={ notifyRoles.map(
									( slug ) => nameBySlug[ slug ] ?? slug
								) }
								suggestions={ Object.values( nameBySlug ) }
								onChange={ ( tokens ) =>
									setNotifyRoles(
										tokens
											.map(
												( token ) =>
													slugByName[ token ] ?? token
											)
											.filter(
												( slug ) => nameBySlug[ slug ]
											)
									)
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								__experimentalExpandOnFocus
							/>
							<p className="wplalr-field-help">
								{ __(
									'Send an email when a user with one of these roles signs in. Leave empty to disable alerts.',
									'wp-login-logout-redirect'
								) }
							</p>
						</div>
						<div className="wplalr-settings-group">
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								type="email"
								label={ __(
									'Notification email',
									'wp-login-logout-redirect'
								) }
								help={ __(
									'Where alerts and digests are sent. Defaults to the site admin email.',
									'wp-login-logout-redirect'
								) }
								value={ notificationEmail }
								onChange={ setNotificationEmail }
							/>
						</div>
						<div className="wplalr-settings-group">
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Digest summary',
									'wp-login-logout-redirect'
								) }
								help={ __(
									'Send a scheduled roll-up of login activity.',
									'wp-login-logout-redirect'
								) }
								value={ digest }
								options={ DIGEST_OPTIONS }
								onChange={ setDigest }
							/>
						</div>
						<Button
							variant="primary"
							type="submit"
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ isSaving && <Spinner /> }
							{ __( 'Save Changes', 'wp-login-logout-redirect' ) }
						</Button>
					</CardBody>
				</Card>
			</form>
		</div>
	);
};

export default OthersSettings;

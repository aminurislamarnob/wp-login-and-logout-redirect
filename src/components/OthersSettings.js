import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';

const RETENTION_OPTIONS = [
	{ label: __( '7 days', 'wp-login-logout-redirect' ), value: '7' },
	{ label: __( '30 days', 'wp-login-logout-redirect' ), value: '30' },
	{ label: __( '90 days', 'wp-login-logout-redirect' ), value: '90' },
	{ label: __( 'Keep forever', 'wp-login-logout-redirect' ), value: '0' },
];

const OthersSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ enableLogs, setEnableLogs ] = useState( false );
	const [ retentionDays, setRetentionDays ] = useState( '30' );

	useEffect( () => {
		setEnableLogs( !! settings.wplalr_enable_logs );
		setRetentionDays( String( settings.wplalr_logs_retention_days ?? 30 ) );
	}, [ settings.wplalr_enable_logs, settings.wplalr_logs_retention_days ] );

	const handleSubmit = ( event ) => {
		event.preventDefault();
		saveSettings( {
			wplalr_enable_logs: enableLogs,
			wplalr_logs_retention_days: parseInt( retentionDays, 10 ),
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

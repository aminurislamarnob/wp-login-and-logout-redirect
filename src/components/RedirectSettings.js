import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Spinner,
	TextControl,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';
import PlaceholderHint from './PlaceholderHint';

const RedirectSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ loginRedirect, setLoginRedirect ] = useState( '' );
	const [ logoutRedirect, setLogoutRedirect ] = useState( '' );

	useEffect( () => {
		setLoginRedirect( settings.wplalr_login_redirect ?? '' );
		setLogoutRedirect( settings.wplalr_logout_redirect ?? '' );
	}, [ settings.wplalr_login_redirect, settings.wplalr_logout_redirect ] );

	const handleSubmit = ( event ) => {
		event.preventDefault();
		saveSettings( {
			wplalr_login_redirect: loginRedirect,
			wplalr_logout_redirect: logoutRedirect,
		} );
	};

	const homeUrl = window.wplalrAdmin?.homeUrl ?? '';

	return (
		<div
			className="wplalr-section wplalr-section--narrow"
			id="wplalr-redirect-settings"
		>
			<form onSubmit={ handleSubmit }>
				<Card className="wplalr-form-header-card">
					<CardBody className="wplalr-form-section-header">
						<h3 className="wplalr-section-title">
							{ __(
								'Default Redirect URLs',
								'wp-login-logout-redirect'
							) }
						</h3>
						<p className="wplalr-section-description">
							{ __(
								'These default URLs are used when no rule on the Rules tab matches the user. Leave blank to use the WordPress defaults (admin for login, home for logout).',
								'wp-login-logout-redirect'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="wplalr-form-section-body">
						<div className="wplalr-settings-group">
							<TextControl
								type="url"
								label={ __(
									'Login Redirect URL',
									'wp-login-logout-redirect'
								) }
								help={ __(
									'Enter the URL to which the user will be redirected after a successful login.',
									'wp-login-logout-redirect'
								) }
								value={ loginRedirect }
								placeholder={ `${ homeUrl }/example-login-redirect-link/` }
								onChange={ setLoginRedirect }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="wplalr-settings-group">
							<TextControl
								type="url"
								label={ __(
									'Logout Redirect URL',
									'wp-login-logout-redirect'
								) }
								help={ __(
									'Enter the URL to which the user will be redirected after a successful logout.',
									'wp-login-logout-redirect'
								) }
								value={ logoutRedirect }
								placeholder={ `${ homeUrl }/example-logout-redirect-link/` }
								onChange={ setLogoutRedirect }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</div>
						<PlaceholderHint />
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

export default RedirectSettings;

import domReady from '@wordpress/dom-ready';

import './admin.scss';
import { render } from '@wordpress/element';

import SettingsForm from './components/SettingsForm';

domReady( function () {
	const htmlOutput = document.getElementById(
		'wplalr-login-logout-plugin-settings'
	);
	const siteData = loginlogoutData;
	const roles = [ ...siteData?.roles ].map( ( role ) => {
		return { label: role.name, value: role.role };
	} );

	if ( htmlOutput ) {
		render( <SettingsForm availableRoles={ roles } />, htmlOutput );
	}
} );

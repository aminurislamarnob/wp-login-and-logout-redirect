/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './components/LayoutStyles.css';
import PageShell from './shared/PageShell';
import SettingsApp from './views/SettingsApp';
import AuditLogsApp from './views/AuditLogsApp';
import SessionsApp from './views/SessionsApp';

/**
 * Each WordPress submenu page renders exactly one of these mount nodes. The
 * single bundle is enqueued on all three screens; on boot we render whichever
 * view's node is present, wrapped in the shared PageShell.
 */
const VIEWS = [
	{
		id: 'wplalr-settings',
		page: 'wplalr_login_logout_redirect',
		Component: SettingsApp,
	},
	{
		id: 'wplalr-audit-logs',
		page: 'wplalr_audit_logs',
		Component: AuditLogsApp,
	},
	{
		id: 'wplalr-sessions',
		page: 'wplalr_sessions',
		Component: SessionsApp,
	},
];

document.addEventListener( 'DOMContentLoaded', () => {
	for ( const { id, page, Component } of VIEWS ) {
		const container = document.getElementById( id );

		if ( container ) {
			createRoot( container ).render(
				<PageShell current={ page }>
					<Component />
				</PageShell>
			);
			break;
		}
	}
} );

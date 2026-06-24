/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * The Audit Logs view.
 *
 * Placeholder shell — the LogsViewer + useLogs hook land here per
 * docs/audit-logs-plan.md (Phase 1).
 */
const AuditLogsApp = () => (
	<div className="wplalr-section">
		<Card>
			<CardBody className="wplalr-form-section-body">
				<h2 className="wplalr-section-title">
					{ __( 'Audit Logs', 'wp-login-logout-redirect' ) }
				</h2>
				<p className="wplalr-section-description">
					{ __(
						'Login, logout and failed-login history will appear here.',
						'wp-login-logout-redirect'
					) }
				</p>
			</CardBody>
		</Card>
	</div>
);

export default AuditLogsApp;

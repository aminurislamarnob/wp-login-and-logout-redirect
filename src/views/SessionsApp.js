/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * The Logged-in Users view.
 *
 * Placeholder shell — the SessionsViewer + useSessions hook land here per
 * docs/logged-in-users-plan.md (Phase 2).
 */
const SessionsApp = () => (
	<div className="wplalr-section">
		<Card>
			<CardBody className="wplalr-form-section-body">
				<h2 className="wplalr-section-title">
					{ __( 'Logged-in Users', 'wp-login-logout-redirect' ) }
				</h2>
				<p className="wplalr-section-description">
					{ __(
						'Active login sessions and force-logout controls will appear here.',
						'wp-login-logout-redirect'
					) }
				</p>
			</CardBody>
		</Card>
	</div>
);

export default SessionsApp;

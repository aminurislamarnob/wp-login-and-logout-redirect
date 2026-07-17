/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	RulesIcon,
	CodeBracketIcon,
	ChartBarIcon,
	UsersIcon,
	EnvelopeIcon,
	SparklesIcon,
} from '../components/icons';

const RELEASE_VERSION = '4.0.0';

/**
 * The What's New view: a showcase of everything that shipped in this release.
 *
 * Content mirrors the readme.txt changelog for the current version; keep the
 * two in sync when cutting a release.
 */
const WhatsNewApp = () => {
	const adminUrl = window.wplalrAdmin?.adminUrl || '';

	const features = applyFilters( 'wplalr_whats_new_features', [
		{
			key: 'rules',
			accent: 'blue',
			icon: RulesIcon,
			title: __( 'Redirect rule engine', 'wp-login-logout-redirect' ),
			description: __(
				'Target users by role, specific user or capability, and set which rule wins with drag-and-drop priority.',
				'wp-login-logout-redirect'
			),
			link: `${ adminUrl }admin.php?page=wplalr_login_logout_redirect#/rules`,
			linkLabel: __( 'Build a rule', 'wp-login-logout-redirect' ),
		},
		{
			key: 'placeholders',
			accent: 'violet',
			icon: CodeBracketIcon,
			title: __( 'URL placeholders', 'wp-login-logout-redirect' ),
			description: __(
				'Personalize destinations with {{username}}, {{user_slug}} and {{website_url}} — each with one-click copy.',
				'wp-login-logout-redirect'
			),
			link: `${ adminUrl }admin.php?page=wplalr_login_logout_redirect`,
			linkLabel: __( 'Try a placeholder', 'wp-login-logout-redirect' ),
		},
		{
			key: 'logs',
			accent: 'green',
			icon: ChartBarIcon,
			title: __( 'Audit Logs', 'wp-login-logout-redirect' ),
			description: __(
				'A searchable, filterable history of logins, logouts, failed and forced logouts — with at-a-glance stat cards.',
				'wp-login-logout-redirect'
			),
			link: `${ adminUrl }admin.php?page=wplalr_audit_logs`,
			linkLabel: __( 'Open Audit Logs', 'wp-login-logout-redirect' ),
		},
		{
			key: 'sessions',
			accent: 'slate',
			icon: UsersIcon,
			title: __( 'Logged-in Users', 'wp-login-logout-redirect' ),
			description: __(
				'See every active session and force logout per session, per user, in bulk — or everyone at once.',
				'wp-login-logout-redirect'
			),
			link: `${ adminUrl }admin.php?page=wplalr_sessions`,
			linkLabel: __( 'View sessions', 'wp-login-logout-redirect' ),
		},
		{
			key: 'alerts',
			accent: 'amber',
			icon: EnvelopeIcon,
			title: __( 'Email alerts & digests', 'wp-login-logout-redirect' ),
			description: __(
				'Get notified when selected roles log in, plus daily, weekly or monthly activity digests in your inbox.',
				'wp-login-logout-redirect'
			),
			link: `${ adminUrl }admin.php?page=wplalr_login_logout_redirect#/others`,
			linkLabel: __( 'Set up alerts', 'wp-login-logout-redirect' ),
		},
		{
			key: 'ui',
			accent: 'blue',
			icon: SparklesIcon,
			title: __( 'Modern settings UI', 'wp-login-logout-redirect' ),
			description: __(
				'A fresh React-powered admin with tabbed navigation, collapsible rule cards and instant save feedback.',
				'wp-login-logout-redirect'
			),
		},
	] );

	return (
		<>
			{ /* Hero */ }
			<div className="wplalr-section">
				<Card className="wplalr-whatsnew-hero">
					<CardBody>
						<span className="wplalr-whatsnew-version">
							{ /* translators: %s: plugin version number. */ }
							{ __( 'Version', 'wp-login-logout-redirect' ) }{ ' ' }
							{ RELEASE_VERSION }
						</span>
						<h2 className="wplalr-whatsnew-title">
							{ __(
								'Meet Entryway — WP Login & Logout Redirect',
								'wp-login-logout-redirect'
							) }
						</h2>
						<p className="wplalr-whatsnew-subtitle">
							{ __(
								'WP Login and Logout Redirect has a new name: Entryway – WP Login and Logout Redirect. Same plugin, same settings — now with a full redirect rule engine, activity auditing, session control, email alerts and a brand-new admin experience.',
								'wp-login-logout-redirect'
							) }
						</p>
						<div className="wplalr-whatsnew-hero-actions">
							<Button
								variant="primary"
								href={ `${ adminUrl }admin.php?page=wplalr_login_logout_redirect#/rules` }
							>
								{ __(
									'Explore the rule engine',
									'wp-login-logout-redirect'
								) }
							</Button>
							<Button
								variant="secondary"
								href="https://wordpress.org/plugins/wp-login-and-logout-redirect/#developers"
								target="_blank"
								rel="noreferrer"
							>
								{ __(
									'Full changelog',
									'wp-login-logout-redirect'
								) }
							</Button>
						</div>
					</CardBody>
				</Card>
			</div>

			{ /* Feature grid */ }
			<div className="wplalr-section">
				<div className="wplalr-whatsnew-grid">
					{ features.map(
						( {
							key,
							accent,
							icon: Icon,
							title,
							description,
							link,
							linkLabel,
						} ) => (
							<Card
								key={ key }
								className={ `wplalr-whatsnew-card wplalr-whatsnew-card--${ accent }` }
							>
								<CardBody>
									<span className="wplalr-whatsnew-card-icon">
										<Icon />
									</span>
									<h3 className="wplalr-whatsnew-card-title">
										{ title }
									</h3>
									<p className="wplalr-whatsnew-card-description">
										{ description }
									</p>
									{ link && (
										<a
											className="wplalr-whatsnew-card-link"
											href={ link }
										>
											{ linkLabel }
											<span aria-hidden="true"> →</span>
										</a>
									) }
								</CardBody>
							</Card>
						)
					) }
				</div>
			</div>
		</>
	);
};

export default WhatsNewApp;

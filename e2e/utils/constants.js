/**
 * Shared constants for the e2e suite.
 *
 * Everything this suite creates on the site is prefixed `wplalr_e2e_` so teardown
 * can find and remove exactly what it made, and a human can tell test data apart
 * from real data at a glance. The suite runs against a real dev site, not a
 * disposable container, so that distinction matters.
 */

/** Prefix for every user, rule and page this suite creates. */
const PREFIX = 'wplalr_e2e_';

/** Password shared by the throwaway accounts. */
const PASSWORD = 'wplalr-e2e-pass-9d2f';

/**
 * The accounts the suite drives.
 *
 * A dedicated admin means the suite never needs the real admin's password and
 * never signs the real admin out.
 */
const USERS = {
	admin: {
		login: `${ PREFIX }admin`,
		role: 'administrator',
		email: 'wplalr-e2e-admin@example.com',
	},
	editor: {
		login: `${ PREFIX }editor`,
		role: 'editor',
		email: 'wplalr-e2e-editor@example.com',
	},
	subscriber: {
		login: `${ PREFIX }subscriber`,
		role: 'subscriber',
		email: 'wplalr-e2e-subscriber@example.com',
	},
	customer: {
		login: `${ PREFIX }customer`,
		role: 'subscriber',
		email: 'wplalr-e2e-customer@example.com',
	},
};

/** Plugin options the suite resets between tests. */
const PLUGIN_OPTIONS = [
	'wplalr_login_redirect',
	'wplalr_logout_redirect',
	'wplalr_redirect_rules',
	'wplalr_enable_logs',
	'wplalr_logs_retention_days',
	'wplalr_logs_notification_email',
	'wplalr_logs_notify_roles',
	'wplalr_logs_digest',
];

/** Admin page slugs. */
const PAGES = {
	settings: 'admin.php?page=wplalr_login_logout_redirect',
	logs: 'admin.php?page=wplalr_audit_logs',
	sessions: 'admin.php?page=wplalr_sessions',
};

module.exports = { PREFIX, PASSWORD, USERS, PLUGIN_OPTIONS, PAGES };

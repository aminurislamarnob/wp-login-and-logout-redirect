/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

/**
 * Cross-page navigation for the shared admin chrome.
 *
 * Each item maps to a WordPress admin submenu page (a full page load). Pro
 * extensions can add their own pages/links via the `wplalr_admin_nav_items`
 * filter without patching the free app.
 *
 * @return {Array<{page: string, label: string}>} Nav items.
 */
export const getNavItems = () =>
	applyFilters( 'wplalr_admin_nav_items', [
		{
			page: 'wplalr_login_logout_redirect',
			label: __( 'Redirect Options', 'wp-login-logout-redirect' ),
		},
		{
			page: 'wplalr_audit_logs',
			label: __( 'Audit Logs', 'wp-login-logout-redirect' ),
		},
		{
			page: 'wplalr_sessions',
			label: __( 'Logged-in Users', 'wp-login-logout-redirect' ),
		},
		{
			page: 'wplalr_whats_new',
			label: __( "What's New", 'wp-login-logout-redirect' ),
		},
	] );

/**
 * Shared Jest setup for the React admin app tests.
 *
 * Runs after the environment is ready but before each test module is
 * imported. Anything components read at module scope (e.g. OthersSettings'
 * `ROLES` from `window.wplalrAdmin`) must therefore be defined here at the
 * top level, not inside `beforeEach`.
 */
import '@testing-library/jest-dom';

// @wordpress/components (popovers via floating-ui, token fields) expect the
// observer APIs that jsdom does not implement.
class NoopObserver {
	observe() {}
	unobserve() {}
	disconnect() {}
	takeRecords() {
		return [];
	}
}
global.ResizeObserver = NoopObserver;
global.IntersectionObserver = NoopObserver;

// The localized data the plugin's Assets class prints on real admin screens.
window.wplalrAdmin = {
	adminUrl: 'https://example.test/wp-admin/',
	assetsUrl:
		'https://example.test/wp-content/plugins/wp-login-and-logout-redirect/assets',
	homeUrl: 'https://example.test',
	roles: {
		administrator: 'Administrator',
		editor: 'Editor',
		subscriber: 'Subscriber',
	},
};

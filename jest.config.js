/**
 * Jest config for the React admin app (run via `npm run test:js`).
 *
 * Extends the @wordpress/scripts preset. Because this file exists, wp-scripts
 * hands config duty entirely to Jest, so anything the preset provides that we
 * override (transform, setupFilesAfterEnv) must be re-included explicitly.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	// The repo has no Babel config; use the wp-scripts transform so JSX and
	// the @wordpress/babel-preset-default features compile in tests.
	transform: {
		'\\.[jt]sx?$': require.resolve(
			'@wordpress/scripts/config/babel-transform'
		),
	},
	setupFilesAfterEnv: [
		require.resolve(
			'@wordpress/jest-preset-default/scripts/setup-test-framework.js'
		),
		'<rootDir>/tests/js/jest.setup.js',
	],
	// uuid@14 (pulled in by @wordpress/notices) ships ESM only; let Babel
	// transform it instead of Jest choking on its `export` statements.
	transformIgnorePatterns: [ '/node_modules/(?!(uuid)/)' ],
	testMatch: [ '<rootDir>/src/**/__tests__/**/*.test.js' ],
	// Playwright specs in e2e/ and PHP fixtures in tests/ and vendor/ are not
	// Jest's business.
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/e2e/',
		'/assets/',
	],
};

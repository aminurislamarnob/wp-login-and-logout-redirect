# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Login and Logout Redirect is a WordPress plugin that redirects users to configurable URLs on login/logout. It also tracks and displays each user's last login time in the admin users table.

## Commands

```bash
# Install PHP dependencies (dev)
composer update

# Install PHP dependencies (production, no dev)
composer update --no-dev

# Run PHPCS linting
composer phpcs

# Auto-fix PHPCS issues
composer phpcbf

# Install the WordPress test suite + scratch DB (once, before the first test run)
composer test:install

# Run PHPUnit (all suites / just one)
composer test
composer test:unit
composer test:rest
composer test:integration

# Install JS dependencies
npm install

# Build the React admin bundle (outputs to assets/build/)
npm run build

# Watch/rebuild during development
npm run start

# Build release ZIP (runs npm install + npm run build internally)
chmod +x bin/build.sh && bin/build.sh
```

## Architecture

Namespace: `PluginizeLab\WpLoginLogoutRedirect` — PSR-4 autoloaded from `includes/`.

**Entry point:** `wp-login-logout-redirect.php` — defines constants, loads autoloader, calls `WpLoginLogoutRedirect::init()`.

**Core classes (all in `includes/`):**

- `WpLoginLogoutRedirect` — singleton bootstrap. Defines constants, registers activation/deactivation hooks, registers REST routes on `rest_api_init`, and initializes all other classes via a `$container` array accessible through `__get()`.
- `Settings` — registers the admin menu page ("Redirect Options") and renders the React app mount point (`<div id="wplalr-settings">`). No longer uses the Settings API.
- `REST\SettingsController` — `WP_REST_Controller` exposing `GET`/`POST` at `wplalr/v1/settings` (cap: `manage_options`). Reads/writes the two existing options `wplalr_login_redirect` and `wplalr_logout_redirect`. This is what the React settings page talks to.
- `Assets` — on the settings screen (`toplevel_page_wplalr_login_logout_redirect`) enqueues the webpack build from `assets/build/admin/` (using `script.asset.php` for deps/version), localizes `window.wplalrAdmin`, and loads `wp-components` styles. Also registers/enqueues the front-end script/style.
- `Redirection` — hooks into `login_redirect`, `woocommerce_login_redirect`, and `wp_logout` to perform the actual redirects using the stored options. Falls back to `admin_url()` for login and `home_url()` for logout when no URL is configured.
- `UserLoginTime` — stores `wplalr_last_login` user meta on login, adds a sortable "Last Login" column to the WP admin users list.

**React admin app (`src/`, built with `@wordpress/scripts`):**

- `src/admin.js` — mounts `<App>` (HashRouter + `SettingsProvider`) onto `#wplalr-settings`.
- `src/context/SettingsContext.js` — fetches/saves settings via `apiFetch` against `/wplalr/v1/settings`; exposes `settings`, `isLoading`, `isSaving`, `saveSettings`; fires `@wordpress/notices` snackbars.
- `src/components/` — `Layout` (header + tabbed hash-nav + `SnackbarList` + loading skeleton), `SettingsHeader`, `RedirectSettings` (the Redirects tab), `icons`, `LayoutStyles.css`.
- `webpack.config.js` — single entry `admin/script: ./src/admin.js`, output to `assets/build/`. Source (`src/`, `webpack.config.js`, `package.json`) is excluded from the release ZIP via `.distignore`; the prebuilt `assets/build/` ships.

## Tests

PHPUnit against the real WordPress core test suite (no mocking framework) — each
test runs inside a transaction that is rolled back.

- `tests/bootstrap.php` boots WP with the plugin loaded and creates the audit-log
  table once up front (DDL implicitly commits in MySQL, so it cannot be per-test).
- `tests/TestCase.php` — base class; resets plugin options and log rows per test.
- `tests/REST/RestTestCase.php` — adds a `WP_REST_Server` plus `dispatch()` /
  `acting_as()` helpers. `reboot_server()` re-registers routes for tests that add a
  schema filter (route args are frozen at `rest_api_init`).
- `bin/install-wp-tests.sh` falls back to the wordpress-develop git mirror when
  `svn` is absent (the default on macOS).
- Defaults assume Homebrew MySQL on `127.0.0.1` with `root`/`root`; override by
  calling the script directly with your own credentials.

Six tests are `markTestIncomplete()` — they encode intended behavior for known
defects (see the message on each). Delete the marker line when the bug is fixed.

### The integration suite

`tests/Integration/` drives the plugin through its real WordPress hooks rather
than calling plugin methods: `wp_signon()`, the `login_redirect` filter,
`wp_logout`, the cron hooks, and the REST routes. It exists to cover the seams
between classes, which is where the defects it found all live.

- `IntegrationTestCase` — the plugin bootstraps itself during the test bootstrap,
  so `Redirection` and `UserLoginTime` are **already hooked**; use them as-is,
  because constructing a second instance doubles every callback. `Logger` and
  `Notifier` gate on `wplalr_enable_logs` *in their constructor* and logging is
  off at boot, so they hook nothing — call `enable_logs()` to set the option and
  build the pair, and never before the option is set. `tear_down()` unhooks them.
- `log_out()` catches the redirect via a `wp_redirect` filter that throws
  `RedirectCaught`, since `redirect_after_logout()` ends in `exit()`.
- `act_as_session()` mints a real `logged_in` cookie: `wp_get_session_token()`
  reads the cookie and has no filter, so it is the only way `is_current` works.
- `sign_in()` makes the signed-in user the current user — re-assert an admin
  before any later REST write, or it 403s.

## Coding Standards

- WordPress Coding Standards enforced via PHPCS (`phpcs.xml`)
- PHP 7.4+ compatibility required
- Text domain: `wp-login-logout-redirect`
- Short array syntax `[]` is allowed (DisallowShortArraySyntax disabled)
- Yoda conditions not enforced
- Strict comparisons are enforced as errors

## WP Options

| Option Key | Purpose |
|---|---|
| `wplalr_login_redirect` | URL to redirect after login |
| `wplalr_logout_redirect` | URL to redirect after logout |

## User Meta

| Meta Key | Purpose |
|---|---|
| `wplalr_last_login` | Unix timestamp of user's last login |

## Deployment

GitHub Actions workflows handle deployment to wordpress.org SVN on tag push. Only the repo owner can create tags (enforced by workflow).

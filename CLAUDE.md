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

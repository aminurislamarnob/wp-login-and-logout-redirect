# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Login and Logout Redirect is a WordPress plugin that redirects users to configurable URLs on login/logout. It also tracks and displays each user's last login time in the admin users table.

## Commands

```bash
# Install dependencies (dev)
composer update

# Install dependencies (production, no dev)
composer update --no-dev

# Run PHPCS linting
composer phpcs

# Auto-fix PHPCS issues
composer phpcbf

# Build release ZIP
chmod +x bin/build.sh && bin/build.sh
```

## Architecture

Namespace: `PluginizeLab\WpLoginLogoutRedirect` — PSR-4 autoloaded from `includes/`.

**Entry point:** `wp-login-logout-redirect.php` — defines constants, loads autoloader, calls `WpLoginLogoutRedirect::init()`.

**Core classes (all in `includes/`):**

- `WpLoginLogoutRedirect` — singleton bootstrap. Defines constants, registers activation/deactivation hooks, initializes all other classes via a `$container` array accessible through `__get()`.
- `Settings` — registers the admin menu page ("Redirect Options") and two WP options: `wplalr_login_redirect` and `wplalr_logout_redirect`. Renders `templates/settings-form.php`.
- `Redirection` — hooks into `login_redirect`, `woocommerce_login_redirect`, and `wp_logout` to perform the actual redirects using the stored options. Falls back to `admin_url()` for login and `home_url()` for logout when no URL is configured.
- `UserLoginTime` — stores `wplalr_last_login` user meta on login, adds a sortable "Last Login" column to the WP admin users list.

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

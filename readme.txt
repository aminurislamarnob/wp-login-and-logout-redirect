=== Entryway - WP Login & Logout Redirect ===
Contributors: aminurislam01, pluginizelab
Donate link: https://www.buymeacoffee.com/aiarnob
Tags: login redirect, logout redirect, redirect, login security, audit log
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 4.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Redirect users to any URL after login or logout with powerful per-role rules, a searchable audit log, live session management, and email notifications.

== Description ==

WP Login and Logout Redirect gives you full control over where users land after they sign in or out of your site — from a simple pair of default URLs to powerful per-role redirect rules. On top of that, it records login activity in a searchable audit log, alerts you by email when important users sign in, and lets you see (and force out) everyone currently logged in.

Everything is managed from a fast, modern settings screen built with React — no page reloads, instant feedback, and a clean design that feels right at home in your WordPress admin.

= 🔀 Smart Redirect Rules =

Go beyond one-size-fits-all redirects. Build an ordered list of rules and send different users to different places:

* Target users by **role**, **specific user**, or **capability** — combine multiple conditions in one rule (all must match).
* Rules are evaluated **top to bottom; the first match wins** — reorder them with drag and drop.
* Each rule sets its own login and/or logout destination.
* Toggle rules on and off without deleting them.
* Personalize destination URLs with placeholders: `{{username}}`, `{{user_slug}}` and `{{website_url}}` — one click copies a placeholder to your clipboard.

= 🎯 Default Redirects =

* Set a site-wide login redirect URL and logout redirect URL used whenever no rule matches.
* Leave a field empty to keep the WordPress default (dashboard after login, homepage after logout).
* Works with WooCommerce login too.

= 📋 Audit Log =

Know exactly who signed in, when, and from where:

* Records **logins, logouts, failed logins and forced logouts** with IP address, browser and OS.
* At-a-glance stat cards plus a searchable, filterable event table.
* Automatic cleanup — keep events for 7, 30 or 90 days, or forever.
* Delete single entries or clear the whole log at any time. Logging is off until you enable it.

= 👥 Logged-in Users =

A live view of every active session on your site:

* See who is currently logged in, their role, when they signed in and when the session expires.
* Inspect each user's individual sessions (device, browser, IP).
* Force logout a single session, a user, a selection of users — or everyone at once (with an option to keep yourself logged in).

= 📧 Email Notifications =

* Get an instant email when a user with a role you watch (e.g. Administrator) signs in.
* Receive a **daily, weekly or monthly digest** summarizing login activity.
* Send notifications to any address — defaults to the site admin email.

= 🕐 Last Login Column =

Every user's most recent login date and time appears in a sortable column on the Users → All Users screen.

= Privacy =

When audit logging is enabled, each event stores the user's IP address and browser. Alert and digest emails include this login metadata and are sent only to the configured notification address (the site admin email by default). Notifications are off until you opt in.

== Support ==

If you find this plugin useful, consider supporting its development through a [donation](https://www.buymeacoffee.com/aiarnob).


== Installation ==

= FOR STANDARD INSTALLATION: =

Installing this plugin is very easy just like any other WordPress plugin. Please follow these instructions:

1. In your WordPress admin panel, go to Plugins > New Plugin, search for "WP Login and Logout Redirect" and click on "Install Now"

2. Alternatively, download the plugin and upload the wordpress-login-and-logout-redirect.zip to your plugins directory, which usually is /wp-content/plugins/.

3. Activate the plugin from plugins page.

To configure redirects, click "Redirect Options" in the admin left menu (or the settings link on the plugins page). The Audit Logs and Logged-in Users pages live under the same menu.

== Screenshots ==

1. Default redirect URLs — set site-wide login and logout destinations
2. Redirect rules — per-role/user/capability rules with drag-and-drop priority
3. Rule editor — conditions, destinations and one-click copy placeholders
4. Audit log — stats, search and filters for all login activity
5. Logged-in users — live sessions with force logout controls
6. Audit logging and email notification settings
7. Users last login date and time


== Frequently Asked Questions ==

= Where is the options page to insert redirect links? =
This plugin adds a "Redirect Options" page to the admin left menu. The Audit Logs and Logged-in Users pages are submenu items of the same menu.

= Can I add only login or logout redirect link individually? =
Yes, you can add login or logout link individually or both.

= Can I redirect different user roles to different pages? =
Yes. On the Rules tab, add a rule with a "Role" condition and set its own login/logout URLs. Rules run top to bottom and the first matching rule wins; drag rules to change their priority. When no rule matches, the default URLs are used.

= Can I personalize the redirect URL per user? =
Yes. Use the `{{username}}`, `{{user_slug}}` or `{{website_url}}` placeholders anywhere in a redirect URL — for example `https://example.com/members/{{user_slug}}/`.

= How do I see who signed in to my site? =
Enable logging on Redirect Options → Others, then open the Audit Logs page. Events record the user, time, IP address, browser/OS and the redirect that was applied.

= Can I log out a user remotely? =
Yes. The Logged-in Users page lists all active sessions. You can end a single session or force logout a user, selected users, or everyone at once.

= As an admin can I see every registered users last login date and time? =
Yes, go to Users->All Users from admin dashboard left sidebar menu.



== Changelog ==
= 4.0.0 =
* [Feature] Redirect rule engine: target users by role, specific user or capability with drag-and-drop rule priority.
* [Feature] URL placeholders {{username}}, {{user_slug}} and {{website_url}} with one-click copy.
* [Feature] Audit Logs page: searchable, filterable log of logins, logouts, failed and forced logouts with stat cards.
* [Feature] Logged-in Users page: view active sessions and force logout per session, per user, in bulk or everyone.
* [Feature] Email alerts on login for selected roles and daily/weekly/monthly activity digests.
* [Feature] Modern React settings UI with tabbed navigation and instant save feedback.
* [Improve] Redesigned admin experience: collapsible rule cards, grouped conditions, consistent controls.
* [Fix] Audit Logs now record the URL the user was actually sent to. Previously the redirect column was empty whenever the destination came from the global login/logout setting, and showed the raw, unexpanded placeholder text when a rule used one.
* [Fix] Saving your settings no longer widens a rule to the whole site when one of its conditions refers to a role or user that no longer exists (for example after deactivating the plugin that registered the role). The rule now stays as written and simply matches nobody until that role or user is back.

= 3.1.7 =
* [Improve] Fix PHPCS coding standard issues across all files.
* [Security] Sanitize GET input with sanitize_text_field() and wp_unslash().
* [Security] Use wp_safe_redirect() instead of wp_redirect() for logout redirection.
* [Fix] Use underscores in hook name for WordPress naming convention compliance.

= 3.1.6 =
* [Fix] Fatal error when get_current_screen() is called before it is available.

= 3.1.4 =
* Compatibility check with latest WordPress Version.

= 3.1.1 =
* [Improve] Re-structure full plugin codebase.
* [Improve] Add support to login redirect even if wooCommerce is installed.

= 3.1 =
* Update plugin tags.

= 3.0 =
* Few security update.

= 2.0 =
* Just minor updates like modify tags, check with WordPress version etc.

= 1.1 =
* Added every users last login date & time on admin dashboard all users table.

= 1.0 =
* Initial release.

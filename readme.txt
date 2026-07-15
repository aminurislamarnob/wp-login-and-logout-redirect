=== WP Login and Logout Redirect ===
Contributors: aminurislam01, pluginizelab
Donate link: https://www.buymeacoffee.com/aiarnob
Tags: WP login and logout redirect, wp login logout redirect, wordpress login logout redirect, login redirect, logout redirect
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 3.1.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin enable simple and easy way to redirect user to your chosen page URL after login or logout or both.

== Description ==

This super easy plugin allow you to change WordPress Default Login and Logout Redirection Link.

This plugin adds plugin specific options page on admin left menu (Menu Name: Redirect Options), for inserting redirect login or logout URL. If you will not enter logout redirect url after this plugin installation by default this plugin which will take users to the homepage after logout and will take users to the wp-admin page after login.

This plugin also show each users last/latest login date and time on admin dashboard all users table.

= Plugin Features =
* Redirect users after login to custom URL.
* Redirect users after logout to custom URL.

= Latest Features =
* Every User last login date and time shown on dashbaord all users page.
* Audit logging of login, logout and failed-login events with retention control.
* Email alerts when a user with a selected role signs in, plus daily/weekly/monthly activity digests.

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

To add login and logout redirect URL's click on "Redirect Options" Admin Left Menu or this plugin settings link from plugins page beside deactive link.

== Screenshots ==

1. Plugin settings page
2. Users last login date and time


== Frequently Asked Questions ==

= Where is the options page to insert redirect links? =
This plugin adds plugin options page on admin left menu (Menu Name: Redirect Options).

= Can I add only login or logout redirect link individually? =
Yes, you can add login or logout link individually or both.

= As an admin can I see every registered users last login date and time? =
Yes, go to Users->All Users from admin dashboard left sidebar menu.



== Changelog ==
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

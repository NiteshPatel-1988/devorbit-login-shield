=== DevOrbit Login Shield ===
Contributors: nitspatel
Tags: login, security, hide login, wp-admin, brute force
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hide wp-login.php and wp-admin behind a custom URL of your choice to cut down on brute-force login attempts.

== Description ==

DevOrbit Login Shield lets you change the address of your login page from the default `/wp-login.php` to a slug you choose, such as `/my-login/`. Requests to `wp-login.php`, and requests to `/wp-admin/` from anyone who is not logged in, are redirected to the home page (or shown a 404, your choice) instead of reaching a login form.

This does not change any core files, database table, or rewrite rules. It intercepts the request early and serves WordPress's own login logic at your chosen URL, so every feature of the normal login screen, including lost password, registration, and logout, continues to work exactly as before, just at a different address.

**Features**

* Choose any custom slug for your login page.
* Direct requests to wp-login.php are blocked, and wp-admin is blocked for logged-out visitors.
* Blocked requests are intercepted before WordPress can redirect to the login screen, so your custom slug is never exposed in a Location header.
* Optionally removes the "Log in" and "Register" links themes show to logged-out visitors, so the custom URL stays out of your public HTML.
* Choose whether blocked requests are redirected home or shown a 404.
* Logged-in users, AJAX, cron, and WP-CLI are never affected.
* No rewrite rules, no .htaccess changes, no database schema changes.
* Settings are removed cleanly on uninstall.

**A note on security**

This plugin reduces exposure to automated login scanners and brute-force bots that target `/wp-login.php` by default. It is not a replacement for strong passwords, two-factor authentication, or a login rate-limiting plugin, and it should be used alongside those, not instead of them.

== Installation ==

1. Upload the `devorbit-login-shield` folder to `/wp-content/plugins/`, or install directly through Plugins > Add New in your WordPress admin.
2. Activate the plugin through the Plugins screen.
3. Go to Settings > DevOrbit Login Shield and choose your custom slug.
4. Decide whether to keep public login links, using the "Public login links" setting on the same screen.
5. **Bookmark the new login URL shown on that screen before navigating away.**

== Frequently Asked Questions ==

= I got locked out. How do I get back in? =

Connect to your site via FTP/SFTP or your hosting file manager and delete or rename the `devorbit-login-shield` folder inside `/wp-content/plugins/`. This deactivates the plugin immediately and restores normal access to `/wp-login.php` and `/wp-admin/`.

= Why has the "Log in" link disappeared from my site? =

Because the "Public login links" setting is on. Those links are generated from your login URL, so any page that renders one, such as the Meta widget or a comment form set to require registration, would print your custom URL in its HTML for anyone to read. With the setting on, the links are removed instead. Turn it off under Settings > DevOrbit Login Shield if your site needs a visible login link.

= Do password-protected posts still work? =

Yes. The password form posts to `wp-login.php?action=postpass`, which only sets a cookie and redirects, so that one request is allowed through. It never renders a login form.

= Does this work with multisite? =

It works on a per-site basis. Logged-in network admins are not affected, but logged-out requests to `/wp-admin/network/` are blocked like any other wp-admin URL.

= Will this break plugins that use AJAX or handle form submissions while logged out? =

No. Requests to `admin-ajax.php` and `admin-post.php` are always allowed through, whether or not the visitor is logged in.

= Does this change my database or add rewrite rules? =

No. It stores two small options (your chosen slug and your preferred blocked-request behaviour) and removes them automatically when the plugin is deleted.

== Screenshots ==

1. The Login URL settings screen under Settings.

== Changelog ==

= 1.0.3 =
* Fixed: the default "login" slug was on the reserved list, so saving the settings unchanged showed an error.
* Fixed: wp-login.php is now served with its global variables bound correctly when shown at the custom URL.
* Fixed: requests to /WP-Login.php with different letter casing are now blocked on case-insensitive servers.
* Fixed: only the first "wp-login.php" in a generated URL is replaced, so redirect_to values are left untouched.
* Fixed: a possible fatal error when a login URL was generated before WordPress finished loading user functions.
* Fixed: 404 mode now loads the theme template through load_template() so template variables are in the correct scope.
* Tweaked: removed a redundant wp_unslash() call and tidied code comments.
* Tested up to WordPress 7.1.

= 1.0.2 =
* Added: a "Settings" link on the plugin's row on the Plugins screen, for quicker access to Settings > DevOrbit Login Shield.

= 1.0.1 =
* Fixed: "Show a 404 Not Found page" mode now renders the active theme's own 404 template (falling back to its index template), instead of a generic "Not found." message.
* Fixed: the settings screen showed the "Settings saved." notice twice after saving.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
Bug fix and compatibility release. The default "login" slug can now be saved, and login pages and 404 mode load more reliably. Tested with WordPress 7.1.

= 1.0.2 =
Adds a "Settings" quick link on the Plugins screen. No other behavior changes.

= 1.0.1 =
Bug fix release: blocked-request 404 mode now shows your theme's real 404 page, and the settings screen no longer duplicates the "Settings saved." notice.

= 1.0.0 =
Initial release.

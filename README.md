# DevOrbit Login Shield

![Version](https://img.shields.io/badge/version-1.0.3-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License](https://img.shields.io/badge/license-GPLv2%2B-green)

Hide `wp-login.php` and `wp-admin` behind a custom URL of your choice to cut down on brute-force login attempts.

## Overview

DevOrbit Login Shield changes the address of your WordPress login page from the default `/wp-login.php` to a slug you choose, such as `/my-login/`. Requests to `wp-login.php`, and requests to `/wp-admin/` from anyone who is not logged in, are redirected to the home page (or shown a 404, your choice) instead of reaching a login form.

The plugin does not change core files, database tables, or rewrite rules. It intercepts the request early and serves WordPress's own login logic at your chosen URL, so lost password, registration, and logout keep working exactly as before, just at a different address.

## Features

- Choose any custom slug for your login page.
- Direct requests to `wp-login.php` are blocked, and `wp-admin` is blocked for logged-out visitors.
- Blocked requests are intercepted before WordPress can redirect to the login screen, so your custom slug is never exposed in a `Location` header.
- Optionally removes the "Log in" and "Register" links themes show to logged-out visitors, keeping the custom URL out of your public HTML.
- Choose whether blocked requests are redirected home or shown the theme's 404 page.
- Logged-in users, AJAX, cron, and WP-CLI are never affected.
- No rewrite rules, no `.htaccess` changes, no database schema changes.
- Settings are removed cleanly on uninstall.

## Requirements

| Requirement | Version |
| ----------- | ------- |
| WordPress   | 6.0 or higher |
| PHP         | 7.4 or higher |

## Installation

1. Download this repository as a ZIP, or clone it into `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/NiteshPatel-1988/devorbit-login-shield.git
   ```
2. Make sure the folder is named `devorbit-login-shield`.
3. Activate the plugin from the **Plugins** screen.
4. Go to **Settings > DevOrbit Login Shield** and choose your custom slug.
5. Decide whether to keep public login links using the **Public login links** setting.
6. **Bookmark the new login URL shown on that screen before navigating away.**

## Settings

| Setting | Default | Description |
| ------- | ------- | ----------- |
| Login slug | `login` | The path where the login form is served. WordPress reserved words are rejected. |
| Blocked request behavior | Redirect home | Redirect blocked requests to the home page, or render the active theme's 404 template. |
| Public login links | On | Removes "Log in" and "Register" links from public pages so the custom URL is not printed in your HTML. |

The plugin stores three options: `devorbit_login_shield_slug`, `devorbit_login_shield_redirect_mode`, and `devorbit_login_shield_hide_links`. All are removed on uninstall.

## FAQ

**I got locked out. How do I get back in?**
Connect over FTP/SFTP or your host's file manager and delete or rename the `devorbit-login-shield` folder inside `/wp-content/plugins/`. This deactivates the plugin immediately and restores normal access to `/wp-login.php` and `/wp-admin/`.

**Why has the "Log in" link disappeared from my site?**
The **Public login links** setting is on. Those links are generated from your login URL, so anything that renders one (the Meta widget, a comment form requiring registration) would print your custom URL for anyone to read. Turn the setting off if your site needs a visible login link.

**Do password-protected posts still work?**
Yes. The password form posts to `wp-login.php?action=postpass`, which only sets a cookie and redirects, so that one request is allowed through. It never renders a login form.

**Does it work with multisite?**
It works per site. Logged-in network admins are unaffected; logged-out requests to `/wp-admin/network/` are blocked.

**Will it break plugins that use AJAX or handle forms while logged out?**
No. Requests to `admin-ajax.php` and `admin-post.php` are always allowed through.

**Does it change my database or add rewrite rules?**
No. It stores only the options listed above.

## A note on security

This plugin reduces exposure to automated scanners and brute-force bots that target `/wp-login.php` by default. It is **not** a replacement for strong passwords, two-factor authentication, or a login rate-limiting plugin. Use it alongside those, not instead of them.

## Changelog

### 1.0.3
- Fixed: the default `login` slug was on the reserved list, so saving the settings unchanged showed an error.
- Fixed: `wp-login.php` is now served with its global variables bound correctly at the custom URL.
- Fixed: requests to `/WP-Login.php` with different letter casing are now blocked on case-insensitive servers.
- Fixed: only the first `wp-login.php` in a generated URL is replaced, so `redirect_to` values are left untouched.
- Fixed: a possible fatal error when a login URL was generated before WordPress finished loading user functions.
- Fixed: 404 mode now loads the theme template through `load_template()`.
- Tweaked: removed a redundant `wp_unslash()` call and tidied code comments.
- Tested up to WordPress 7.1.

### 1.0.2
- Added a "Settings" link on the plugin's row on the Plugins screen.

### 1.0.1
- Fixed: 404 mode now renders the active theme's own 404 template (falling back to its index template).
- Fixed: the settings screen showed the "Settings saved." notice twice.

### 1.0.0
- Initial release.

## Project structure

```
devorbit-login-shield/
├── devorbit-login-shield.php          # Plugin bootstrap and activation defaults
├── includes/
│   └── class-devorbit-login-shield.php # Request interception, URL filters, settings page
├── uninstall.php                      # Removes stored options
├── readme.txt                         # WordPress.org readme
└── LICENSE.txt
```

## Contributing

Issues and pull requests are welcome. For security-related reports, please open a private security advisory on GitHub instead of a public issue.

## License

Released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html). See [LICENSE.txt](LICENSE.txt).

## Author

Built by [NitsPatel](https://github.com/NiteshPatel-1988).

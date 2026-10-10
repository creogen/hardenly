=== Sitewarden ===
Contributors: merkushin
Tags: security, security scanner, vulnerability, hardening, site health
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find security problems on your WordPress site and learn how to fix them. Read-only and offline by default.

== Description ==

Sitewarden checks your WordPress settings, users, plugins, themes and files, gives your site a security score, and tells you what to fix and how. It only reads: it never changes, moves or deletes anything on your site.

* Outdated WordPress, plugins, themes and PHP.
* Risky configuration: debug output, file editing in the dashboard, missing security keys.
* Users: an "admin" username, too many administrators, usernames that can be listed.
* Files: PHP in uploads, backups and `.git` or `.env` in the web root, recently changed files.
* Suspicious code patterns, such as hidden code that decodes and runs itself. These are warnings to review, not proof of an infection.
* HTTPS, certificate expiry, security headers, XML-RPC.

Scans run in small steps in the background, so they are safe on shared hosting.

= Privacy =

By default Sitewarden sends nothing outside your site. It stores its settings, the latest scan results and each user's last login date in your database. Deleting the plugin removes all of it.

To see what visitors see, some checks send requests from your site to itself (loopback requests, the same kind Site Health uses): the home page (security headers), `/?author=` and `/wp-json/wp/v2/users` (username listing), `xmlrpc.php`, the uploads folder (folder listing), and `debug.log`, `.git` and `.env` when those files exist. The certificate check opens a connection to your own site's address to read its expiry date. None of these requests go to other sites.

Version checks read the update information WordPress already downloads; Sitewarden makes no requests of its own for them.

= Source code =

The uncompiled JavaScript and CSS and the build instructions are at https://github.com/creogen/sitewarden.

== Installation ==

1. In your dashboard, go to **Plugins → Add New Plugin** and search for "Sitewarden", or upload the plugin zip.
2. Activate the plugin.

== Frequently Asked Questions ==

= A question users ask =

The answer.

== Screenshots ==

1. Describe the first screenshot, .wordpress-org/screenshot-1.png.

== Changelog ==

= 1.0.0 =
* Initial release.
* Requires WordPress 6.2 or later.
* Scanner core: checks run in the background in small batches, with a security score and a dashboard.
* 29 offline checks: configuration, users, versions, files, server and HTTP, and database.
* Records each user's last login date, for the inactive administrators check.
* Suspicious code check: looks for patterns often used to hide or inject code in PHP files.

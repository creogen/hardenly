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

By default Sitewarden sends nothing outside your site. If you turn on email reports, a summary (score and problem titles, no file paths) is sent with your site's own email setup to the address you choose. It stores its settings, the latest scan results and each user's last login date in your database. Deleting the plugin removes all of it.

To see what visitors see, some checks send requests from your site to itself (loopback requests, the same kind Site Health uses): the home page (security headers), `/?author=` and `/wp-json/wp/v2/users` (username listing), `xmlrpc.php`, the uploads folder (folder listing), and `debug.log`, `.git` and `.env` when those files exist. The certificate check opens a connection to your own site's address to read its expiry date. None of these requests go to other sites.

Version checks read the update information WordPress already downloads; Sitewarden makes no requests of its own for them. Optional online services are described under "External services".

= External services =

Sitewarden works offline by default. These services are used only after you turn them on in Sitewarden > Settings > Online services:

**WordPress.org checksums** ("Compare WordPress core and plugin files with WordPress.org"). During a scan, Sitewarden downloads the official file checksums to find changed or added files. It sends your WordPress version and language to api.wordpress.org, and the slug and version of each installed plugin to downloads.wordpress.org. No site address, users or file contents are sent. [WordPress.org privacy policy](https://wordpress.org/about/privacy/).

**WPScan** (vulnerability lookup, with your own API key). During a scan, Sitewarden asks WPScan about known vulnerabilities. It sends your WordPress version and the slugs of installed plugins and themes (not their versions) to wpscan.com, with your API key. Results are kept for a day. The free WPScan plan is for non-commercial use. [WPScan terms](https://wpscan.com/terms/), [privacy policy](https://automattic.com/privacy/).

**Patchstack** (vulnerability lookup, with your own API key). During a scan, Sitewarden sends your WordPress version and the slugs and versions of installed plugins and themes to patchstack.com, with your API key. Results are kept for a day. [Patchstack terms](https://patchstack.com/terms-and-conditions/), [privacy policy](https://patchstack.com/privacy-policy/).

You choose at most one vulnerability provider, and lookups run only after you tick the consent box for it.

= Source code =

The uncompiled JavaScript and CSS and the build instructions are at https://github.com/creogen/sitewarden.

== Installation ==

1. In your dashboard, go to **Plugins → Add New Plugin** and search for "Sitewarden", or upload the plugin zip.
2. Activate the plugin.

== Frequently Asked Questions ==

= Does Sitewarden change or delete anything on my site? =

No. It only reads files and settings and tells you what to fix. Ignoring a finding only hides it in Sitewarden.

= Can it scan automatically? =

Yes. In Sitewarden > Settings, choose a daily or weekly scan, and optionally an email summary. Scans run through WP-Cron, so they start with the first visit after the scheduled time.

= Why does a finding say it is suspicious, not infected? =

The code check looks for patterns often used to hide code, but some legitimate plugins use them too. Review the file; if it is expected, add it to "Files to skip".

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
* Settings screen: choose check groups, the recently changed files threshold and files to skip.
* Ignore findings you have reviewed, and skip files from the dashboard.
* Daily or weekly automatic scans, with an optional email summary.
* Optional online services: compare core and plugin files with WordPress.org, and look up known vulnerabilities with WPScan or Patchstack using your own API key. All off by default.

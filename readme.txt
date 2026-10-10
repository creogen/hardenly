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

Sitewarden checks your WordPress site for security problems, gives it a security score, and tells you what to fix and how. It only reads: it never changes, moves or deletes anything on your site, and it works offline unless you turn on an online service.

**What it checks**

* Outdated WordPress, plugins, themes and PHP, and plugins and themes you no longer use.
* Risky configuration: PHP errors shown to visitors, file editing in the dashboard, missing or weak security keys, HTTPS for logins.
* Users: an "admin" username, too many administrators, administrators who have not logged in for months, usernames visitors can list.
* Files: PHP files in uploads, files anyone on the server can change, backups and database dumps in public folders, a public debug log, exposed `.git` and `.env` files, recently changed files.
* Suspicious code: patterns often used to hide or inject code, such as code that decodes and runs itself. These are warnings to review, not proof of an infection.
* Server: HTTPS, certificate expiry, security headers, folder listing, XML-RPC.
* Database: the default table prefix and the size of autoloaded options.

**Optional online services** (off by default)

* Compare WordPress core and plugin files with the official copies on WordPress.org, to find changed or added files.
* Look up known vulnerabilities in your installed versions with WPScan or Patchstack, using your own API key.

**Made for everyday use**

* Scans run in small steps in the background, so they are safe on shared hosting and you can leave the page.
* Each finding explains why it matters and how to fix it. Ignore findings you have reviewed, and skip files you trust.
* Daily or weekly automatic scans, with an optional email summary when something changes.
* Clear wording, no colour-only status, and no upsells: everything is in the free plugin.

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
3. Go to **Sitewarden** in the admin menu and click **Run first scan**.
4. Optional: in **Sitewarden → Settings**, choose what to scan, turn on automatic scans and email reports, and set up online services.

== Frequently Asked Questions ==

= Does Sitewarden change or delete anything on my site? =

No. It only reads files and settings and tells you what to fix. Ignoring a finding only hides it in Sitewarden.

= Can it scan automatically? =

Yes. In Sitewarden > Settings, choose a daily or weekly scan, and optionally an email summary. Scans run through WP-Cron, so they start with the first visit after the scheduled time.

= Does it slow down my site? =

No. Sitewarden does nothing on the front end. Scans run in the background in short steps of a few seconds, through WP-Cron or while the Sitewarden screen is open.

= Does it work on multisite? =

Sitewarden scans one site at a time. Activate it on each site you want to scan; activating it for the whole network is not supported.

= Do I need an API key? =

No. All built-in checks work without one. A key from WPScan or Patchstack is only needed if you want to look up known vulnerabilities in your installed versions.

= Why does a finding say it is suspicious, not infected? =

The code check looks for patterns often used to hide code, but some legitimate plugins use them too. Review the file; if it is expected, add it to "Files to skip".

== Screenshots ==

1. The dashboard: security score, problem counts and findings with how to fix them.
2. A scan in progress. Scans run in small background steps.
3. Settings: what to scan, files to skip, automatic scans and email reports.
4. Optional online services, each off until you turn it on.

== Changelog ==

= 1.0.0 =
* Initial release.

# Sitewarden: Specification

Read-only WordPress security scanner for the WordPress.org directory. Built on `merkushin/wpplugin`
(`composer create-project merkushin/wpplugin sitewarden`). Follow `AGENTS.md` from the template; this file
only adds product decisions.

## 1. Identifiers

| Item | Value |
| --- | --- |
| Name | Sitewarden |
| Slug / text domain / main file | `sitewarden` / `sitewarden` / `sitewarden.php` |
| PHP namespace | `Sitewarden\` (PSR-4, `src/`) |
| Prefix | `sitewarden_` (options, transients, hooks, nonces, cron); `sitewarden-` for script handles |
| Min PHP | 7.4 |
| Min WP | 6.2; keep header, `readme.txt` and `phpcs.xml.dist` in sync |
| License | GPL-2.0-or-later |

The slug `sitewarden` was free on wordpress.org when the project started (checked 2026-10-10). Fallback:
`sitewarden-security-scanner`.

## 2. Goals and non-goals

**Goals (v1)**
- Scan the site and report problems with severity and a clear fix hint.
- Work fully offline by default. External data is opt-in.
- Safe on shared hosting: batched, resumable, no timeouts.
- Dashboard with score + findings, and optional email report.

**Non-goals (v1)**
- No auto-fix, quarantine, deletion or any write to site files/DB (beyond the plugin's own data).
- No firewall, login protection or live traffic monitoring.
- No malware signature database. Heuristics only.
- No multisite network admin support (single site only; network-activation is blocked or documented).
- No bundled vulnerability database.

## 3. Scan modules

Each check implements `Sitewarden\Scanner\Check`: `id()`, `title()`, `group()`, `run( Context ): Result[]`.
A `Result` has: `check_id`, `status` (`pass|warning|fail|info|skipped`), `severity` (`info|low|medium|high|critical`),
`message` (shown as the finding's title), `remediation`, optional `description` (why it matters), `details` (e.g. file
paths, capped at 500, each cut to 500 characters) and `key` (tells apart several results of one check).

A long-running check calls `Context::should_yield()` while it works and, when true, `Context::pause( $cursor )`; the
scanner calls it again in the next batch with that cursor in `Context::cursor()`. A check that throws is reported as
`skipped` and the scan goes on. Results that are not `Result` objects or name another check are dropped.

### 3.1 Offline (default on)

Each check's id, severity and class are in the table below the summary. Severities: problems are `fail`
(or `warning` for heuristics); `info` results are notes that do not lower the score.

| Group | Checks |
| --- | --- |
| Versions | WP core, plugins, themes outdated, read from the `update_core` / `update_plugins` / `update_themes` site transients that WordPress already maintains (no own HTTP calls). PHP version vs. supported branches (small bundled table, updated per release). Inactive plugins/themes still installed. |
| Config | `WP_DEBUG` / `WP_DEBUG_DISPLAY` on in production, `DISALLOW_FILE_EDIT` not set, `FORCE_SSL_ADMIN`, default `wp_` table prefix, salts missing or default, `WP_ENVIRONMENT_TYPE`. |
| Users | Username `admin` exists, users with admin role count, admin without recent login (needs last-login data; skip if unavailable), user enumeration via `?author=N` and REST `/wp/v2/users`. |
| Files | PHP files in `uploads/`, world-writable files/dirs (`wp-config.php`, `.htaccess`), leftover `readme.html`, `license.txt`, `wp-config-sample.php`, backup/archive files in web root (`*.sql`, `*.zip`, `*.bak`, `*.old`), `.git`/`.env` exposure in web root, recently modified PHP files (threshold configurable). |
| Code patterns | Heuristic scan of PHP files for obfuscation and dangerous constructs (`eval(base64_decode`, `gzinflate`+`eval`, `assert` on request vars, `preg_replace /e`, long encoded blobs, `$_REQUEST` passed to `system/exec/passthru`). Report as **warning**, never as "infected". Per-file allowlist for false positives. |
| Server/HTTP | Site URL uses HTTPS, certificate expiry (via local stream context to own host), security headers on the home page (`X-Frame-Options`/CSP `frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`), directory listing on `uploads/`, XML-RPC enabled, `wp-cron` exposure. Loopback requests only (own site), documented in `readme.txt`. |
| Database | Table prefix, autoloaded options size warning, leftover transients are out of scope. |

Built-in checks (M2), in `src/Scanner/Checks/<Group>/`:

| Id | Class | Reports |
| --- | --- | --- |
| `config.debug_display` | `DebugDisplayCheck` | PHP errors shown to visitors: high in production, low elsewhere |
| `config.file_edit` | `FileEditCheck` | Dashboard file editor on (no `DISALLOW_FILE_EDIT`/`_MODS`): medium |
| `config.force_ssl_admin` | `ForceSslAdminCheck` | HTTPS site without `FORCE_SSL_ADMIN`: low; skipped without HTTPS |
| `config.salts` | `SaltsCheck` | Keys missing, shorter than 32, placeholder or repeated: high |
| `config.environment` | `EnvironmentTypeCheck` | Not `production`: note |
| `users.admin_username` | `AdminUsernameCheck` | User "admin": medium if administrator, else low |
| `users.admin_count` | `AdminCountCheck` | More than 3 administrators: low |
| `users.inactive_admins` | `InactiveAdminsCheck` | Administrators without a login in 90 days: low. Logins are recorded by `Users\LastLogin` (user meta `sitewarden_last_login`); skipped until 90 days of records exist |
| `users.enumeration` | `UserEnumerationCheck` | `/?author=N` redirect or public `/wp/v2/users` (loopback): low |
| `versions.core` | `CoreVersionCheck` | Update in the same branch: high; newer branch: medium |
| `versions.plugins` / `versions.themes` | `PluginUpdatesCheck` / `ThemeUpdatesCheck` | Updates available: high |
| `versions.php` | `PhpVersionCheck` | Past end of security support: high; ending within 180 days: low |
| `versions.inactive` | `InactiveExtensionsCheck` | Inactive plugins and themes (keeps the active theme, its parent and the newest default theme): low |
| `server.https` | `HttpsCheck` | Home or site URL without HTTPS: high |
| `server.certificate` | `CertificateCheck` | Expired: critical; < 14 days: high; < 30 days: medium (`Site\TlsProbe`) |
| `server.headers` | `SecurityHeadersCheck` | Missing security headers on the home page (loopback): low |
| `server.directory_listing` | `DirectoryListingCheck` | Listing of `uploads/` (loopback): medium |
| `server.xmlrpc` | `XmlRpcCheck` | `system.listMethods` answered (loopback): low |
| `server.wp_cron` | `WpCronCheck` | WP-Cron runs on visits: note |
| `database.table_prefix` | `TablePrefixCheck` | `wp_` prefix: low |
| `database.autoload_size` | `AutoloadSizeCheck` | Autoloaded options over 800 KB (Site Health's limit): low |
| `files.permissions` | `PermissionsCheck` | World-writable WordPress folder, `wp-config.php` (also one level up) or `.htaccess`: high |
| `files.leftovers` | `LeftoverFilesCheck` | `readme.html`, `license.txt`, `wp-config-sample.php`: low |
| `files.backups` | `BackupFilesCheck` | In the WordPress or wp-content folder (not subfolders): database dumps and config copies high, other archives medium |
| `files.debug_log` | `DebugLogCheck` | `wp-content/debug.log`: high if downloadable (loopback), else low |
| `files.exposed` | `ExposedFilesCheck` | `.git`, `.svn`, `.hg`, `.env` in the WordPress folder: critical if readable (loopback), else low |
| `files.uploads_php` | `UploadsPhpCheck` | PHP files in uploads, ignoring comment-only `index.php` files: high |
| `files.recently_modified` | `RecentlyModifiedCheck` | PHP files changed within the threshold (default 7 days): note |

Code patterns (M3): `code.suspicious` (`Code\SuspiciousCodeCheck`) walks all PHP files (`.php`, `.phtml`, `.inc`
and variants, first 1 MB of each) with `Scanner\Code\PatternMatcher` and reports one **warning**, high if any
file matches a high rule, else medium. Details: `path:line excerpt (rules)`. Comments are ignored (tokenizer);
the per-file allowlist is "Files to skip".

| Rule | Severity | Matches |
| --- | --- | --- |
| `eval_decode` | high | `eval`/`assert` of `base64_decode`, `gzinflate`, `str_rot13`, `hex2bin` and similar |
| `eval_request` | high | `eval`/`assert`/`create_function` with `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE` in the call |
| `exec_request` | high | `system`, `exec`, `shell_exec`, `passthru`, `popen`, `proc_open`, `pcntl_exec` with request data (not methods) |
| `request_function` | high | `$_GET['f'](...)`: calling a function named by request data |
| `include_request` | high | `include`/`require` of a path with request data |
| `preg_replace_e` | medium | `preg_replace` with the `/e` modifier |
| `encoded_blob` | medium | base64 string of 2000+ characters in a file that also calls `base64_decode`/`gzinflate`/`eval` |
| `hex_escapes` | medium | 12+ `\xNN` escapes in a row that decode to readable code (not binary tables) |
| `chr_chain` | medium | 8+ concatenated `chr(N)` calls |

Samples live in `tests/fixtures/code/{suspicious,benign}/*.txt`. Benign baseline: no matches in WordPress core,
Plugin Check and the dev `vendor/` (about 7,000 files).

File walks use `Scanner\FileWalker` (resumable, never follows symlinks) through `FileWalkCheck`; paths in "Files to
skip" are not walked. All raw file access is in `Site\Filesystem`; loopback requests go through `Site\Loopback`
(own site only, no redirects, 512 KB body limit, `https_local_ssl_verify`).

### 3.2 Online (opt-in, off by default, user consent in settings)

1. **WordPress.org checksums**: core and plugin file integrity via `api.wordpress.org` (the same API WP core uses).
   Own toggle. Documented in `readme.txt` as an external service.
2. **Vulnerability providers**: `VulnerabilityProvider` interface; v1 adapters for WPScan and Patchstack.
   The user supplies their own API key and accepts the provider's terms. No provider is bundled or preselected.
   With no provider configured, the Versions group still works offline and the dashboard shows "vulnerability
   lookup not configured".

Only plugin/theme slugs and versions are sent to providers, never site URL, user data or file contents.
Document exactly what is sent in `readme.txt` (WordPress.org guideline 7).

## 4. Architecture

```
sitewarden.php                bootstrap only
src/Plugin.php                wires hooks, VERSION constant
src/Admin/Menu.php            top-level menu + capability gate
src/Admin/DashboardPage.php   score, findings table, "Run scan" button
src/Admin/SettingsPage.php    modules, thresholds, email, providers
src/Rest/ScanController.php   scan start/status/tick/stop and report endpoints
src/Scanner/Scanner.php       orchestrates checks, batching, progress
src/Scanner/Check.php         interface
src/Scanner/Context.php       settings, time budget, memory guard, cursor
src/Scanner/CheckRegistry.php built-in checks + `sitewarden_checks`, validated, in group order
src/Scanner/Group.php         group ids, labels and run order
src/Scanner/Result.php        value object
src/Scanner/Checks/...        one class per check (grouped by folder)
src/Scanner/FileWalker.php    resumable directory iterator (cursor)
src/Scanner/Score.php         score calculation
src/Storage/ReportStore.php   latest report and history (options)
src/Storage/RunStore.php      state of the active run (option)
src/Storage/Settings.php      settings with defaults (option)
src/Storage/Lock.php          atomic lock (INSERT IGNORE, like WP_Upgrader::create_lock)
src/Vulnerability/...         provider interface + adapters
src/Report/EmailReport.php    summary email
src/Cron/Schedule.php         scheduled scan
```

### 4.1 Scan lifecycle
1. Admin clicks "Run scan" (or the schedule fires) and the scanner creates a **run** with state `queued`.
2. A WP-Cron single event (`sitewarden_scan_tick`) processes work in batches under a time budget (default 10 s)
   and memory guard, then reschedules itself until done. While the dashboard is open it calls
   `POST sitewarden/v1/scan/tick` (5 s batches), so a scan also progresses where WP-Cron is slow or disabled.
3. Fast checks (config, users, versions, headers) run first; the file walk is cursor-based and resumable.
4. On completion the scanner stores the report, computes the score, and sends the email if enabled.
5. Only one run at a time, and only one batch at a time (atomic lock with expiry). A run without progress for
   15 minutes is marked `failed`; a stopped run is marked `stopped`. Partial results are discarded in both cases.

Action Scheduler is **not** bundled. WP-Cron is enough and keeps the plugin small.

### 4.2 Storage
- `sitewarden_settings`: all settings in one option (`Storage\Settings`): `disabled_groups`, `modified_days`,
  `skip_files`, `ignored` (finding id => message, date, user), `schedule` (off/daily/weekly), `email_enabled`,
  `email_only_changes`, `email_to`. Every write goes through `Settings::sanitize()`; the Settings screen posts to
  `options.php` and is mapped by `Settings::sanitize_form()`.
- Ignored findings: a finding id is the check id, plus `:key` for checks with several results. `Report\Summary`
  leaves ignored findings out of score and counts; `Report\ReportRefresher` recomputes the latest report and its
  history entry whenever the settings change (`update_option_sitewarden_settings`).
- `sitewarden_latest_report`: full latest report (findings capped, e.g. 500 per check).
- `sitewarden_report_history`: last 10 summaries (date, score, counts).
- `sitewarden_run_state`: option for the active (or last failed/stopped) run.
- `sitewarden_lock`: option row used as the batch lock, written with direct queries (see `Lock`).
- No custom tables in v1. Everything removed in `uninstall.php`, including cron events.

### 4.3 Score
0 to 100, start at 100, subtract weighted penalties per failed/warning finding by severity
(critical 25, high 15, medium 8, low 3, caps per group so one group cannot zero the score). Weights live in one
class and are covered by unit tests. Show the score as an indicator, not a guarantee.

### 4.4 Schedule and email (optional, off by default)
Automatic scans: off/daily/weekly, a WP-Cron event `sitewarden_scheduled_scan` (`Cron\Schedule`) kept in sync with
the setting on every settings save; first run an hour after turning it on. Both cron hooks are cleared on
deactivation and uninstall.

Email (`Report\EmailReport`), after **automatic** scans only. Recipient defaults to the admin email, editable.
Plain-text summary: score, change since last scan, counts by severity, top 10 problems (titles only), link to the
dashboard. No file paths or other details from disk. Uses `wp_mail()`. With "only on changes" (default on), sends
only when the score dropped or a high/critical problem appeared that the previous scan did not have (history
summaries keep their high/critical finding ids for this).

### 4.5 Security of the plugin itself
- Every admin page, action and REST route: `manage_options` + nonce / `permission_callback`.
- Escape all output, including file paths shown from disk. Treat file names and file contents as untrusted.
- File reads: `ABSPATH`-bound, no symlink following outside it, size cap per file, no execution or `include`.
- Provider API keys stored in the settings option, never printed back in full, never logged.
- No remote scripts/fonts. No telemetry.

## 5. Admin UI
Design: https://claude.ai/artifact/C6fX7ZS2Vm7emLwMhvdMHa (native WP admin look, four screens).
- Top-level menu "Sitewarden" with two submenu pages, **Dashboard** and **Settings** (no in-page tabs).
- Dashboard, first run: what the scan does (read-only, offline, background) and a "Run first scan" button.
- Dashboard, scanning: progress bar, files checked, current folder, status per check group, "Stop scan".
  Previous results stay visible, dimmed, until the new report replaces them.
- Dashboard, results: score gauge with change since last scan and a short label ("Needs attention"), counts by
  severity, last scans, a notice when vulnerability lookup is not configured, filters (severity, passed, ignored),
  findings grouped by severity (or check group), each with group tag, details, "How to fix" and "Ignore this
  finding" / "Ignore this file". Lower severities start collapsed; passed checks are one collapsed row.
- Settings sections: What to scan (check groups, modified-files threshold, files to skip), Schedule and email,
  Online services (off by default, each option says exactly what is sent, masked API key, consent checkbox),
  Ignored findings (with Restore). "Ignore this file" adds the path to "Files to skip".
- Progress via REST polling (`@wordpress/api-fetch`). Assets only on Sitewarden screens. Entries:
  `assets/src/admin/`. i18n via `@wordpress/i18n`.
- Accessible: no colour-only status (icon + text), real buttons and labels.

### 5.1 Extension points
v1 is free with nothing locked (WordPress.org forbids trialware). A few public hooks let add-ons, including a
possible future paid add-on, extend the plugin without changing it:
- Filter `sitewarden_checks` (`Check[]`): add, remove or replace checks.
- Filter `sitewarden_vulnerability_providers` (`VulnerabilityProvider[]`, keyed by id): add providers.
- Action `sitewarden_scan_completed` (`array $report`): fires after a report is stored, before the email.
- Filter `sitewarden_score_weights` (`array`): adjust severity weights and group caps.

Hooks are documented in `readme.txt` (FAQ) and covered by tests. Anything registered through them is validated
(must implement the interface) so a broken add-on cannot break a scan.

## 6. Quality gates
- `make check` must pass (WPCS, PHPCompatibility 7.4, PHPStan, PHPUnit, JS lint/tests, POT, version check).
- `make dist && make smoke` after any new WPAL service or WordPress call; add stand-ins to `tests/smoke/dist.php`.
- `make plugin-check` before every release.
- Unit tests for: each check (pass/fail fixtures), `Score`, `FileWalker` resume, pattern matcher
  (true positives + known-benign samples), email trigger logic, REST permissions.
- Filesystem access: check which WPAL service covers it. If none, isolate raw PHP file functions in one
  `Filesystem` wrapper class so Plugin Check findings (direct filesystem calls) are justified in one place with
  `phpcs:ignore` + reason.

## 7. Milestones
1. **M0 Scaffold**: create from template, rename, slug check, `make check` green.
2. **M1 Core**: `Check`/`Result`/`Scanner`, storage, score, WP-Cron batching, REST, minimal dashboard.
3. **M2 Offline checks**: Config, Users, Versions, Files, Server/HTTP.
4. **M3 Code patterns**: heuristics, allowlist, false-positive tests.
5. **M4 Reporting**: settings page, history, email, scheduling.
6. **M5 Online (opt-in)**: checksums, provider interface + WPScan/Patchstack adapters, consent UI, `readme.txt`
   external-services section.
7. **M6 Release**: `readme.txt`, screenshots/banners, `docs/FIRST-RELEASE.md` review checklist, submit.

## 8. Decisions (2026-10-10)
1. Minimum WordPress version: 6.2.
2. Menu: top-level "Sitewarden" with Dashboard and Settings submenus (two pages). Under Tools only if it ever
   shrinks to one page.
3. "Recently modified files": simple mtime threshold, default 7 days, configurable.
4. Both WPScan and Patchstack adapters ship in v1.
5. Free only, no paid tier planned; public extension points in 5.1.

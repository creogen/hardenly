# AGENTS.md

Hardenly is a WordPress plugin meant for the WordPress.org plugin directory. It supports PHP 7.4+
and the WordPress version in "Requires at least" (plugin header and `readme.txt`).

## Commands

| Command | What it does |
| --- | --- |
| `make check` | Everything CI checks on the sources. **Must pass before you finish.** |
| `make test` / `vendor/bin/phpunit --filter Name` | PHP tests |
| `make lint-php` / `vendor/bin/phpcbf` | WordPress Coding Standards; phpcbf fixes formatting |
| `make phpstan` | Static analysis |
| `make test-js` / `make lint-js` | JavaScript tests (Vitest) and ESLint/Stylelint |
| `make i18n` | Regenerates `languages/hardenly.pot`; run after changing translatable strings |
| `make docs` | Regenerates `docs/developer/hooks.md` from hook docblocks; run after adding or changing a hook |
| `make dist && make smoke` | Builds the release zip and runs it; run after changing WordPress calls or the build |
| `make plugin-check` | WordPress.org's Plugin Check on the build (Docker) |

## Layout

- `hardenly.php`: plugin header and bootstrap only. Code goes in `src/`.
- `src/`: PSR-4, namespace `Hardenly\`, one class per file named after the class (`src/Admin/SettingsPage.php`).
  `Plugin` wires hooks; put features in their own classes and register them from `Plugin::init()`.
- `tests/unit/`: PHPUnit 9 tests, namespace `Hardenly\Tests`, mirroring `src/`.
- `tests/smoke/dist.php`: runs the release build with stand-ins for WordPress functions.
- `assets/src/<entry>/index.js` (+ CSS it imports): built by `@wordpress/scripts` to `assets/build/<entry>.js`,
  `.css`, `-rtl.css` and `.asset.php`. Entries are listed in `webpack.config.js` and enqueued by `Plugin::enqueue_entry()`.
  JS tests sit next to the code as `*.test.js`.
- Generated, never edit: `assets/build/`, `build/`, `vendor/`, `vendor-prefixed/`.

## Calling WordPress

PHP code calls WordPress through [WPAL](https://github.com/merkushin/WPAL) services, so tests can mock them:

```php
$this->options = ServiceFactory::create_options();   // in the constructor
$this->options->get_option( 'hardenly_settings', [] );
```

- Every public WordPress function is a method with the same name and parameters. To find its service:
  `grep -l "function get_option(" vendor/merkushin/wpal/src/Service/*.php`. The full map is in
  `vendor/merkushin/wpal/docs/services.md`.
- Use the **Service** layer (`Merkushin\Wpal\Service\*`, `ServiceFactory`), not the `Api` layer or `Wpal` class:
  those need PHP 8.4 and the plugin supports 7.4.
- In tests, replace a service with a mock: `ServiceFactory::set_custom_options( $this->createMock( Options::class ) )`.
  See `tests/unit/PluginTest.php`.
- Translation and escaping functions (`__()`, `esc_html()`, ...) and code in `hardenly.php` and `uninstall.php` call
  WordPress directly.
- The release build keeps only the WPAL services `src/` uses. A new service works in tests but must also work in
  `make dist && make smoke`; add stand-ins for new WordPress functions to `tests/smoke/dist.php`.

## Conventions

- PHP 7.4 syntax: no `match`, enums, `readonly`, named arguments, constructor promotion or union types.
  PHPCompatibility in `make lint-php` catches these.
- WordPress Coding Standards with short array syntax. Prefix everything global: options, transients, hooks,
  meta keys, nonces, script handles and global functions with `hardenly_` (or `hardenly-` for handles).
- Security, which WordPress.org review checks first:
  - Check capabilities (`current_user_can()`) before doing anything on a user's behalf.
  - Verify a nonce on every form submission and state-changing request; REST routes need a `permission_callback`.
  - Sanitize input on the way in (`sanitize_text_field()`, `absint()`, ...), escape output late (`esc_html()`,
    `esc_attr()`, `esc_url()`, `wp_kses_post()`).
  - Use `$wpdb->prepare()` for any SQL with variables.
- i18n: every user-facing string goes through `__()` / `esc_html__()` / `_n()` with the `hardenly` text domain and
  literal strings (no variables). Add a `/* translators: */` comment for placeholders. In JavaScript, use
  `@wordpress/i18n`; the plugin loads script translations automatically. Then run `make i18n`.
- Data the plugin stores (options, transients, tables, cron events) must be removed in `uninstall.php`.
- Don't load assets everywhere: return early from the enqueue methods on screens and pages that don't need them.
- Don't call external services or load remote scripts/fonts without the user's consent, and document them in
  `readme.txt` (WordPress.org guideline 7).

## Versions

The version lives in four places: the plugin header, `readme.txt` (Stable tag), `Plugin::VERSION` and `package.json`.
`make version-check` fails when they differ. Add each change to the `== Changelog ==` in `readme.txt`.

## Product

Product rules for Hardenly. The sections above take precedence for style, layout and tooling.

- Read the product spec in `docs/SPEC.md` before starting any milestone. Work milestone by milestone (M0 to M6).
- Hardenly is **read-only**: never modify, delete, move or chmod site files; never write to the DB beyond the
  plugin's own options/transients. No auto-fix code in v1.
- **Offline by default.** Any outbound request (WordPress.org checksums, vulnerability providers) must be behind
  an explicit opt-in setting, send the minimum data, and be listed in `readme.txt` under external services.
- Loopback requests (own site headers/XML-RPC checks) are allowed and must be documented in `readme.txt`.
- Extension points (`hardenly_checks`, `hardenly_vulnerability_providers`, `hardenly_scan_completed`,
  `hardenly_score_weights`) are public API: see `docs/SPEC.md` 5.1. Validate what they return.
  Document each hook with a docblock (description, `@since`, `@param`) directly above its call, then run
  `make docs`; `docs/developer/extending.md` is the hand-written guide.

## Product rules

- Name: `Hardenly`; namespace `Hardenly\`; prefix `hardenly_`; text domain `hardenly`.
- PHP 7.4 syntax only. Call WordPress through WPAL services where one exists (see "Calling WordPress" above).
- One class per file. Each scan check is its own class in `src/Scanner/Checks/` implementing `Check`, with a
  matching test in `tests/unit/Scanner/Checks/`.
- Scans must be batched and resumable: respect the time budget and memory guard in `Context`; never loop over the
  whole filesystem in one request.
- File contents and names from disk are untrusted: escape on output, cap sizes, never `include`/`eval`.
- Heuristic findings are worded as "suspicious", never "malware" or "infected".
- Anything the plugin stores must be removed in `uninstall.php` (options, transients, cron events).
- When adding a check: add it to the spec table in `docs/SPEC.md`, add tests, run `make i18n`, then `make check`.

## Definition of done

`make check` passes; `make dist && make smoke` passes if WordPress calls or the build changed; changelog entry in
`readme.txt`; version synced in all four places when releasing.

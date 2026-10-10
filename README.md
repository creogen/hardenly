# Sitewarden

Security scanner for your WordPress website.

## Development

Requirements: PHP 7.4+, Composer, Node.js 22.22+, [WP-CLI](https://wp-cli.org/) for translations
and Docker for running Plugin Check locally.

```bash
make install     # composer install && npm ci
make check       # coding standards, PHPStan, PHP and JS tests, POT and version checks
make playground  # WordPress in the browser with this plugin active
npm start        # rebuild JavaScript and CSS on change
```

| Where | What |
| --- | --- |
| `sitewarden.php` | Plugin header and bootstrap |
| `src/` | PHP code, PSR-4 namespace `Sitewarden\` |
| `tests/unit/` | PHPUnit tests; WordPress calls are mocked through WPAL |
| `assets/src/admin/` | Admin JavaScript and CSS entry point, built to `assets/build/` |
| `languages/` | Translation template, `make i18n` |
| `uninstall.php` | Cleanup when the plugin is deleted |
| `readme.txt` | The WordPress.org plugin page |
| `.wordpress-org/` | WordPress.org banners, icons, screenshots and Live Preview blueprint |

WordPress functions are called through [WPAL](https://github.com/merkushin/WPAL) services, so the
code can be unit-tested without WordPress. [AGENTS.md](AGENTS.md) has the conventions and
[docs/SPEC.md](docs/SPEC.md) the product specification.

## Release

```bash
make dist          # builds sitewarden.zip
make smoke         # loads the built plugin and runs its hooks
make plugin-check  # WordPress.org's Plugin Check on the build
```

The first release goes through WordPress.org review: see
[docs/FIRST-RELEASE.md](docs/FIRST-RELEASE.md). After approval, pushing a version tag publishes
the plugin: see `.github/workflows/release.yml`.

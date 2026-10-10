# Extending Hardenly

Hardenly loads its classes when WordPress loads plugins, so hook in from your own plugin as usual.
Your plugin can be active without Hardenly; load your classes that implement Hardenly's interfaces
inside the hook callbacks, which only run when Hardenly is active.

Hardenly validates everything it gets back from these hooks. An entry that has the wrong type or
repeats an id is dropped, so a broken extension cannot break a scan. Full parameter reference:
[Hooks](hooks.md).

## Add a check

A check implements `Hardenly\Scanner\Check` and returns `Hardenly\Scanner\Result` objects. Register it
with the `hardenly_checks` filter:

```php
add_filter(
	'hardenly_checks',
	function ( array $checks ): array {
		require_once __DIR__ . '/src/AdminEmailCheck.php';
		$checks[] = new My_Plugin\AdminEmailCheck();
		return $checks;
	}
);
```

```php
namespace My_Plugin;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Context;
use Hardenly\Scanner\Group;
use Hardenly\Scanner\Result;

class AdminEmailCheck implements Check {
	public function id(): string {
		return 'my_plugin.admin_email';
	}

	public function title(): string {
		return __( 'Admin email', 'my-plugin' );
	}

	public function group(): string {
		return Group::CONFIG;
	}

	public function run( Context $context ): array {
		if ( ! str_ends_with( (string) get_option( 'admin_email' ), '@example.com' ) ) {
			return [ Result::pass( $this->id(), __( 'The admin email is a real address.', 'my-plugin' ) ) ];
		}

		return [
			Result::fail(
				$this->id(),
				Result::SEVERITY_LOW,
				__( 'The admin email is a placeholder', 'my-plugin' ),
				__( 'Set a real address in Settings > General.', 'my-plugin' ),
				[],
				__( 'Security notices from WordPress go to this address.', 'my-plugin' )
			),
		];
	}
}
```

Rules for checks:

- **Id.** Lower-case letters, digits, `_`, `-` and `.`, at most 100 characters. Prefix it with your
  plugin's name so it cannot clash with a built-in check. Users ignore findings by id, so keep it stable.
- **Group.** One of the `Group` constants (`config`, `users`, `versions`, `server`, `database`, `files`,
  `code`, `online`) or your own id. Users can turn the built-in groups off in the settings, and your
  check is skipped with its group. A group of your own always runs, after the built-in ones, and is
  shown by its id.
- **Results.** `Result::pass()`, `fail()`, `warning()` (a possible problem a person should review),
  `info()` and `skipped()` (the check could not run). A problem's severity is one of `Result::SEVERITY_*`.
  When one check reports several problems, give each a different `key` (the `Result` constructor's
  eighth argument), so they can be ignored separately.
- **Read only.** Hardenly never changes a site, and checks must not either. Do not call external
  services unless the user has agreed to it.
- **Untrusted data.** Messages and details are escaped when shown, but keep them short: Hardenly keeps
  at most 500 details of 500 characters each per result.

Removing a check works the same way: filter it out of `$checks` by `id()`.

### Long checks

A scan runs in batches of a few seconds. A check that can take longer, such as a walk over files, asks
`$context->should_yield()` as it works. When it returns `true`, the check saves where it stopped with
`$context->pause( $cursor )` and returns the results it has so far. In the next batch, Hardenly calls
`run()` again with that cursor in `$context->cursor()`. Results from every call are kept.

```php
public function run( Context $context ): array {
	$cursor  = $context->cursor() ?? [ 'offset' => 0 ];
	$results = [];
	$items   = $this->items();

	for ( $i = $cursor['offset']; $i < count( $items ); $i++ ) {
		if ( $context->should_yield() ) {
			$context->pause( [ 'offset' => $i ] );
			return $results;
		}
		// Check $items[ $i ] and add to $results.
	}

	return $results;
}
```

The cursor is stored between requests: keep it small and JSON-safe.

## Add a vulnerability database

A provider implements `Hardenly\Vulnerability\VulnerabilityProvider`. Users can then choose it in
Hardenly > Settings next to WPScan and Patchstack. Register it with the
`hardenly_vulnerability_providers` filter:

```php
add_filter(
	'hardenly_vulnerability_providers',
	function ( array $providers ): array {
		require_once __DIR__ . '/src/MyDatabaseProvider.php';
		$providers[] = new My_Plugin\MyDatabaseProvider();
		return $providers;
	}
);
```

The provider's methods:

- `id()`: lower-case letters, digits, `_` and `-`, at most 50 characters.
- `name()`, `plan_note()`, `signup_url()`, `terms_url()`: shown in the settings.
- `data_sent()`: a translated sentence for the consent checkbox that says exactly what is sent and
  to whom. Hardenly runs the provider only after the user agrees to it. Send nothing more.
- `test( $api_key )`: checks the key and returns a description, such as the plan and requests left.
- `lookup( $component, $api_key )`: returns the `Hardenly\Vulnerability\Vulnerability` objects that
  affect the installed version of a `Hardenly\Vulnerability\Component` (WordPress, a plugin or a theme).

When a request fails, throw `Hardenly\Vulnerability\ProviderException` with a reason
(`ProviderException::AUTH`, `RATE_LIMIT` or `UNAVAILABLE`) and a translated message. The scan then
reports what it could check, and the user sees the message.

## Change the score

The score starts at 100. Each problem that is not ignored takes off a penalty by severity, and one
check group can take off at most `group_cap`. The defaults are:

| Severity | Penalty |
| --- | --- |
| `critical` | 25 |
| `high` | 15 |
| `medium` | 8 |
| `low` | 3 |
| `info` | 0 |

`group_cap` is 40. Change them with `hardenly_score_weights`:

```php
add_filter(
	'hardenly_score_weights',
	function ( array $weights ): array {
		$weights['severity']['medium'] = 10;
		$weights['group_cap']          = 50;
		return $weights;
	}
);
```

Missing and non-numeric values keep their defaults, and negative values count as 0.

## React to a finished scan

`hardenly_scan_completed` fires once per scan, after the report is stored:

```php
add_action(
	'hardenly_scan_completed',
	function ( array $report ): void {
		if ( $report['score'] < 70 ) {
			error_log( sprintf( 'Hardenly score dropped to %d.', $report['score'] ) );
		}
	}
);
```

It can fire in a WP-Cron request or in a REST request from the Hardenly screen, so keep the callback
quick. For a slow job, such as sending the report to another service, schedule a single cron event.
The report's keys are listed in [Hooks](hooks.md#hardenly_scan_completed).

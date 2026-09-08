# Telescope for Laravel 5.0

[![Latest Version](https://img.shields.io/packagist/v/hunglv-fs/laravel5-telescope.svg)](https://packagist.org/packages/hunglv-fs/laravel5-telescope)
[![License](https://img.shields.io/packagist/l/hunglv-fs/laravel5-telescope.svg)](LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/hunglv-fs/laravel5-telescope.svg)](composer.json)

`laravel/telescope` requires Laravel 5.7.7 and PHP 7.1. This gives a **Laravel 5.0** application the
same thing: requests, queries, queue jobs, artisan commands, exceptions, logs, cache and mail, each
grouped into the batch that produced it — plus an **Insights** page that names your N+1 queries and
the statements burning the most time.

No npm, no build step, no Vue. The dashboard is plain Blade.

> 🇻🇳 [Bản tiếng Việt](README.vi.md)

## Why this exists

Telescope cannot be made to run on 5.0 by loosening version constraints. It is built on APIs that
simply did not exist yet:

| laravel/telescope uses | Laravel 5.0 offers | What this package does |
| --- | --- | --- |
| `Illuminate\Database\Events\QueryExecuted` | string event `illuminate.query` with four positional arguments | `QueryWatcher` listens to the string event |
| `JobProcessing` / `JobProcessed` / `JobFailed` | only `illuminate.queue.failed` and `illuminate.queue.stopping` | decorates the `queue.worker` binding |
| `CommandStarting` / `CommandFinished` | only `artisan.start` | `artisan.start` plus a shutdown handler |
| `RequestHandled` | nothing | terminable middleware, prepended to the global stack |
| `Str::uuid()` (ramsey/uuid) | nothing | `HungLv\Telescope\Support\Uuid` |
| package auto-discovery | nothing | register the provider by hand |
| Gate / authorization | no Gate | `Authorize` middleware and `Telescope::auth()` |
| Vue SPA built with Laravel Mix | no build tooling | server-rendered Blade |

The database schema (`telescope_entries`, `telescope_entries_tags`) and the core concepts —
`IncomingEntry`, watchers, an entries repository, batch / tag / family hash — follow the original,
so what you learn here still applies when you eventually upgrade.

## Requirements

- PHP 5.4 or newer
- Laravel 5.0.x
- A database connection (MySQL, PostgreSQL, SQLite — anything the query builder supports)

Verified against Laravel 5.0.14 on PHP 7.0. Laravel 5.1 to 5.6 are untested; several hooks still
exist there, so it may well work, but nothing is promised.

## Installation

```bash
composer require hunglv-fs/laravel5-telescope
```

Register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    'HungLv\Telescope\TelescopeServiceProvider',
],
```

Publish the config file and the migration, then migrate:

```bash
php artisan vendor:publish --provider="HungLv\Telescope\TelescopeServiceProvider"
php artisan migrate
```

Open `/telescope`.

<details>
<summary>Installing without Composer (very old projects)</summary>

Drop the package somewhere such as `packages/telescope` and require its bundled autoloader from
`bootstrap/autoload.php`, right after Composer's:

```php
require __DIR__.'/../packages/telescope/autoload.php';
```

Then copy `config/telescope.php` and the migration into your application by hand.
</details>

## What gets recorded

| Watcher | Captures |
| --- | --- |
| `query` | SQL, bindings, duration, connection, the `file:line` in your app that issued it, `slow` and `duplicate` tags |
| `job` | name, queue, status, attempts, duration, peak memory, payload — with every query the job ran in the same batch |
| `command` | command line, duration, peak memory, query statistics, fatal errors |
| `request` | method, URI, action, status, duration, payload, headers, session, user, response |
| `exception` | class, message, `file:line`, stack trace, grouped by family hash |
| `log` | level, message, context |
| `cache` | hit, missed, set, forget |
| `mail` | subject, recipients, body |
| `event` | every string event (off by default — the 5.0 dispatcher is chatty) |

Everything a request, command or job does shares one **batch**, so opening any entry shows the whole
timeline around it.

## Debugging queue jobs

Each job processed by the worker becomes its own batch: the job entry plus every query, log line and
cache call it made. Open a job under `/telescope/jobs` to see how many queries it fired, how long it
took, and which statements repeated.

The job must run through `queue.worker` — `php artisan queue:work` or `queue:listen`. With the `sync`
driver the job simply runs inside the batch of whatever dispatched it, which is also what you want.

## Insights

`/telescope/insights` answers the questions you actually open a profiler for:

- **N+1 suspects** — the same query shape executed several times inside one batch, with the
  `file:line` that issued it. `in (?, ?)` and `in (?, ?, ?)` are treated as one shape.
- **Query hotspots** — recent queries grouped by shape, ranked by total time spent.
- **Slowest requests, jobs and commands.**

## Configuration

Everything lives in `config/telescope.php`.

| Key | What it does |
| --- | --- |
| `enabled` | `TELESCOPE_ENABLED=false` registers no watcher at all |
| `path` | dashboard URI, default `telescope` |
| `limit` | maximum entries buffered per batch, so a runaway job cannot exhaust memory |
| `storage.database.connection` | `null` pins whatever `database.default` was at boot; set it to keep Telescope's writes out of your application's transactions |
| `ignore_paths` | `Str::is()` patterns; the dashboard itself is always ignored |
| `watchers.query.slow` | milliseconds before a query is tagged `slow` |
| `watchers.query.slow_only` | record slow queries only — the cheap way to run this outside development |
| `watchers.query.backtrace` | resolve the application frame that issued each query |
| `watchers.command.ignore` | commands that should not record themselves (`queue:work`, `telescope:*`, ...) |
| `local_environments`, `allowed_ips` | who may open the dashboard |

## Restricting access

The dashboard shows request payloads, sessions and SQL. By default it only opens in the environments
listed under `local_environments`. To allow specific people elsewhere, register a callback in a
service provider's `boot()`:

```php
\HungLv\Telescope\Telescope::auth(function ($request) {
    return in_array($request->user()->email, ['you@example.com']);
});
```

You can also drop entries before they are stored:

```php
\HungLv\Telescope\Telescope::filter(function ($entry) {
    return $entry->type !== 'query' || $entry->hasTag('slow');
});
```

And record a handled exception yourself:

```php
try {
    $this->import($row);
} catch (\Exception $e) {
    \HungLv\Telescope\Telescope::catchException($e, ['import', 'row:'.$row->id]);
}
```

## Pruning

```bash
php artisan telescope:prune --hours=48   # put this on cron
php artisan telescope:clear              # delete everything
```

## Laravel 5.0 quirks worth knowing

- **Exceptions are recorded once.** 5.0 binds `Psr\Log\LoggerInterface` straight to
  `$app['log']->getMonolog()`, so `Handler::report()` bypasses `Illuminate\Log\Writer` and never
  fires `illuminate.log`. That is why the exception watcher decorates the handler binding instead of
  listening to the log event.
- **The storage connection is pinned at boot.** `migrate --database=other` calls
  `setDefaultConnection()`, which rewrites `database.default` globally; resolving it lazily would
  silently send Telescope's own inserts to the wrong connection.
- **Closure jobs are broken on PHP 7** in Laravel 5.0 itself (SuperClosure 1.x pulls in a php-parser
  release with a class named `String`, reserved since PHP 7). Use `'App\Foo@handle'` or job objects.

## Known limitations

- No live updates: the dashboard is a static Blade page, refresh to see new entries.
- No Redis, model, view or HTTP-client watchers — 5.0 emits no events for them.
- Artisan exit codes are not available (5.0 has no `CommandFinished`); a command is reported as
  `failed` on a fatal error, and tagged `exception` when one was reported.
- No `telescope_monitoring` (the "monitored tags" feature of the original).

## Tests

Both run standalone, no application required:

```bash
php tests/smoke.php   # recording pipeline: batching, tags, N+1 detection, caps, backtrace, row shape
composer install && php tests/views.php   # compiles every view with the real Laravel 5.0 Blade compiler
```

## Credits

An independent implementation for Laravel 5.0, following the schema and watcher architecture of
[laravel/telescope](https://github.com/laravel/telescope) by Taylor Otwell. Not affiliated with or
endorsed by Laravel.

## License

MIT. See [LICENSE](LICENSE).

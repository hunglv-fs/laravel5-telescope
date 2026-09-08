# Changelog

All notable changes to `laravel5-telescope` are documented here.

## 1.0.0

Initial release.

- Watchers for requests, queries, queue jobs, artisan commands, exceptions, logs, cache, mail and events, all wired to the Laravel 5.0 APIs (string events, `queue.worker` decoration, `artisan.start`, terminable middleware, exception handler decoration).
- Blade dashboard: entry lists with tag and full-text filters, entry detail with the batch timeline, and an Insights page reporting N+1 suspects, query hotspots and the slowest requests, jobs and commands.
- `telescope:prune` and `telescope:clear` artisan commands.
- Entry cap per batch, sanitised payloads, hidden parameters and headers, and a storage connection pinned at boot so `migrate --database=other` cannot redirect Telescope's own writes.

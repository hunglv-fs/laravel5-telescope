<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Telescope Master Switch
	|--------------------------------------------------------------------------
	|
	| When disabled no watcher is registered at all, so the package costs
	| nothing but an empty service provider. Keep it off in production
	| unless you are actively hunting something down.
	|
	*/

	'enabled' => env('TELESCOPE_ENABLED', true),

	/*
	|--------------------------------------------------------------------------
	| Dashboard Path
	|--------------------------------------------------------------------------
	*/

	'path' => env('TELESCOPE_PATH', 'telescope'),

	/*
	|--------------------------------------------------------------------------
	| Dashboard Access
	|--------------------------------------------------------------------------
	|
	| The dashboard exposes request payloads, sessions and SQL, so it is only
	| open in these environments, from these IPs, or to whoever the closure
	| registered with Telescope::auth() approves.
	|
	*/

	'local_environments' => ['local'],

	'allowed_ips' => [],

	/*
	|--------------------------------------------------------------------------
	| Storage
	|--------------------------------------------------------------------------
	|
	| `connection` null means "whatever database.default was at boot" - it is
	| pinned then, so a command running with --database=other cannot redirect
	| Telescope's own writes. Pointing Telescope at a separate connection
	| keeps its writes away from the application's own transactions.
	|
	*/

	'storage' => [
		'database' => [
			'connection' => env('TELESCOPE_CONNECTION', null),
			'chunk'      => 500,
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Entry Limit Per Batch
	|--------------------------------------------------------------------------
	|
	| Safety valve: a runaway batch job firing 100k queries would otherwise
	| exhaust memory. Recording stops for that batch once the cap is hit.
	|
	*/

	'limit' => 300,

	/*
	|--------------------------------------------------------------------------
	| Ignored Request Paths
	|--------------------------------------------------------------------------
	|
	| Str::is() patterns, matched against the path without a leading slash.
	| The dashboard itself is always ignored.
	|
	*/

	'ignore_paths' => [
		'assets/*',
		'_debugbar/*',
	],

	/*
	|--------------------------------------------------------------------------
	| Insights
	|--------------------------------------------------------------------------
	|
	| `scan` is how many recent query entries the hotspot report aggregates;
	| `duplicate_threshold` is how many identical query shapes inside one
	| batch count as an N+1 suspect.
	|
	*/

	'insights' => [
		'scan'                => 2000,
		'duplicate_threshold' => 3,
	],

	/*
	|--------------------------------------------------------------------------
	| Watchers
	|--------------------------------------------------------------------------
	*/

	'watchers' => [

		'query' => [
			'enabled'   => true,
			// Milliseconds before a query is tagged "slow".
			'slow'      => 100,
			// Record only slow queries. Cheap way to run this in staging.
			'slow_only' => false,
			// Resolve the application file/line that issued the query.
			'backtrace' => true,
		],

		'request' => [
			'enabled'           => true,
			'slow'              => 1000,
			'response_body'     => true,
			'hidden_parameters' => ['password', 'password_confirmation', '_token', 'token'],
			'hidden_headers'    => ['authorization', 'cookie', 'php-auth-pw', 'x-csrf-token'],
		],

		'command' => [
			'enabled' => true,
			'slow'    => 5000,
			'ignore'  => [
				'queue:work', 'queue:listen', 'queue:restart', 'queue:subscribe',
				'telescope:*', 'tinker', 'serve', 'list',
			],
		],

		'job' => [
			'enabled' => true,
			'slow'    => 5000,
		],

		'exception' => [
			'enabled'     => true,
			'trace_depth' => 30,
		],

		'log' => [
			'enabled' => true,
			// debug, info, notice, warning, error, critical, alert, emergency
			'level'   => 'debug',
		],

		'cache' => [
			'enabled' => true,
			'values'  => true,
			'ignore'  => ['illuminate:queue:restart'],
		],

		'mail' => [
			'enabled' => true,
		],

		'event' => [
			// Off by default: the 5.0 dispatcher is chatty and this watcher
			// sees every single string event.
			'enabled' => false,
			'payload' => true,
			'ignore'  => [
				'illuminate.*', 'cache.*', 'mailer.*', 'artisan.*', 'connection.*',
				'composing:*', 'creating:*', 'router.*', 'locale.*', 'auth.*',
				'eloquent.*', 'kernel.*', 'bootstrapped:*', 'bootstrapping:*',
			],
		],

	],

];

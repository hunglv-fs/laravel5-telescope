<?php namespace HungLv\Telescope;

use Closure;
use Exception;
use HungLv\Telescope\Support\Uuid;
use HungLv\Telescope\Contracts\EntriesRepository;

/**
 * The static entry point of the recorder.
 *
 * Watchers push IncomingEntry objects in here during the lifecycle of a
 * request, an artisan command or a queued job; everything is buffered in
 * memory and written once, at the end, so instrumentation costs one insert
 * instead of one per event.
 */
class Telescope {

	/**
	 * Entries waiting to be persisted.
	 *
	 * @var array
	 */
	public static $entriesQueue = [];

	/**
	 * The batch identifier shared by everything recorded in this lifecycle.
	 *
	 * @var string|null
	 */
	protected static $batchId;

	/**
	 * Whether watchers should currently record.
	 *
	 * @var bool
	 */
	protected static $recording = false;

	/**
	 * Nesting depth of withoutRecording() calls.
	 *
	 * @var int
	 */
	protected static $pauseDepth = 0;

	/**
	 * Callback deciding whether an entry is kept.
	 *
	 * @var \Closure|null
	 */
	protected static $filterCallback;

	/**
	 * Callback deciding who may open the dashboard.
	 *
	 * @var \Closure|null
	 */
	protected static $authCallback;

	/**
	 * Running counters for the current batch.
	 *
	 * @var array
	 */
	public static $stats = [
		'queries'           => 0,
		'query_time'        => 0.0,
		'slow_queries'      => 0,
		'duplicate_queries' => 0,
		'cache_hits'        => 0,
		'cache_misses'      => 0,
		'exceptions'        => 0,
	];

	/**
	 * Occurrences of each query family hash within the current batch.
	 *
	 * @var array
	 */
	protected static $queryHashes = [];

	/**
	 * True once the per-batch entry cap has been hit.
	 *
	 * @var bool
	 */
	public static $limitReached = false;

	/**
	 * Begin a new batch and reset every per-batch counter.
	 *
	 * @return string
	 */
	public static function newBatch()
	{
		static::$batchId = Uuid::v4();
		static::$entriesQueue = [];
		static::$queryHashes = [];
		static::$limitReached = false;
		static::$stats = [
			'queries'           => 0,
			'query_time'        => 0.0,
			'slow_queries'      => 0,
			'duplicate_queries' => 0,
			'cache_hits'        => 0,
			'cache_misses'      => 0,
			'exceptions'        => 0,
		];

		return static::$batchId;
	}

	/**
	 * The current batch id, creating one on first use.
	 *
	 * @return string
	 */
	public static function currentBatchId()
	{
		if (is_null(static::$batchId)) static::newBatch();

		return static::$batchId;
	}

	/**
	 * @return void
	 */
	public static function startRecording()
	{
		static::$recording = true;
	}

	/**
	 * @return void
	 */
	public static function stopRecording()
	{
		static::$recording = false;
	}

	/**
	 * Reopen recording for the entry that closes a batch.
	 *
	 * A request or job that blew past the entry cap is exactly the one worth
	 * having a summary for, so the cap is lifted for that last entry.
	 *
	 * @return void
	 */
	public static function resumeForSummary()
	{
		static::$limitReached = false;

		static::startRecording();
	}

	/**
	 * @return bool
	 */
	public static function isRecording()
	{
		return static::$recording && static::$pauseDepth === 0 && ! static::$limitReached;
	}

	/**
	 * Run a callback with recording suspended (used by Telescope's own writes).
	 *
	 * @param  \Closure  $callback
	 * @return mixed
	 */
	public static function withoutRecording(Closure $callback)
	{
		static::$pauseDepth++;

		try
		{
			$result = $callback();
		}
		catch (Exception $e)
		{
			static::$pauseDepth--;

			throw $e;
		}

		static::$pauseDepth--;

		return $result;
	}

	/**
	 * Bump one of the batch counters.
	 *
	 * @param  string     $key
	 * @param  int|float  $amount
	 * @return void
	 */
	public static function increment($key, $amount = 1)
	{
		if ( ! isset(static::$stats[$key])) static::$stats[$key] = 0;

		static::$stats[$key] += $amount;
	}

	/**
	 * Read one of the batch counters.
	 *
	 * @param  string  $key
	 * @return int|float
	 */
	public static function stat($key)
	{
		return isset(static::$stats[$key]) ? static::$stats[$key] : 0;
	}

	/**
	 * Buffer an entry.
	 *
	 * @param  string  $type
	 * @param  \HungLv\Telescope\IncomingEntry  $entry
	 * @return void
	 */
	public static function record($type, IncomingEntry $entry)
	{
		if ( ! static::isRecording()) return;

		$entry->type($type);

		if (static::$filterCallback && ! call_user_func(static::$filterCallback, $entry)) return;

		static::$entriesQueue[] = $entry;

		if (count(static::$entriesQueue) >= static::limit())
		{
			static::$limitReached = true;
		}
	}

	/**
	 * Record how many times this exact query shape ran in this batch.
	 *
	 * @param  string  $hash
	 * @return int
	 */
	public static function countQueryHash($hash)
	{
		if ( ! isset(static::$queryHashes[$hash])) static::$queryHashes[$hash] = 0;

		return ++static::$queryHashes[$hash];
	}

	/**
	 * Persist and clear the buffer.
	 *
	 * @param  \HungLv\Telescope\Contracts\EntriesRepository  $repository
	 * @return void
	 */
	public static function store(EntriesRepository $repository)
	{
		if (empty(static::$entriesQueue)) return;

		$entries = static::$entriesQueue;

		static::$entriesQueue = [];

		try
		{
			$repository->store($entries);
		}
		catch (Exception $e)
		{
			static::report($e);
		}
	}

	/**
	 * Record an exception by hand, e.g. from a try/catch in a batch job.
	 *
	 * @param  \Exception  $e
	 * @param  array  $tags
	 * @return void
	 */
	public static function catchException(Exception $e, array $tags = [])
	{
		$watcher = new Watchers\ExceptionWatcher;

		$watcher->recordException($e, $tags);
	}

	/**
	 * Register the callback that decides which entries are kept.
	 *
	 * @param  \Closure  $callback
	 * @return void
	 */
	public static function filter(Closure $callback)
	{
		static::$filterCallback = $callback;
	}

	/**
	 * Register the callback that authorises dashboard access.
	 *
	 * @param  \Closure  $callback
	 * @return void
	 */
	public static function auth(Closure $callback)
	{
		static::$authCallback = $callback;
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @return bool
	 */
	public static function check($request)
	{
		if (static::$authCallback)
		{
			return (bool) call_user_func(static::$authCallback, $request);
		}

		return false;
	}

	/**
	 * Maximum entries buffered per batch.
	 *
	 * @return int
	 */
	protected static function limit()
	{
		$limit = static::config('limit', 300);

		return $limit > 0 ? $limit : 300;
	}

	/**
	 * Read a Telescope config value without booting anything heavy.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public static function config($key, $default = null)
	{
		try
		{
			return app('config')->get('telescope.'.$key, $default);
		}
		catch (Exception $e)
		{
			return $default;
		}
	}

	/**
	 * Telescope must never take the application down with it.
	 *
	 * @param  \Exception  $e
	 * @return void
	 */
	public static function report(Exception $e)
	{
		error_log('[telescope] '.get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
	}

}

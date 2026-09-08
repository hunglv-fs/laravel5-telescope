<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Support\Sql;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

/**
 * Laravel 5.0 has no QueryExecuted event object; queries arrive as the
 * string event `illuminate.query` with four positional arguments.
 */
class QueryWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$app['events']->listen('illuminate.query', function($sql, $bindings, $time, $connection) use ($watcher)
		{
			$watcher->recordQuery($sql, $bindings, $time, $connection);
		});
	}

	/**
	 * @param  string  $sql
	 * @param  array   $bindings
	 * @param  float   $time      milliseconds
	 * @param  string  $connection
	 * @return void
	 */
	public function recordQuery($sql, $bindings, $time, $connection)
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			$time = (float) $time;
			$hash = Sql::hash($sql, $connection);
			$slow = $time >= (float) $this->option('slow', 100);
			$occurrence = Telescope::countQueryHash($hash);

			Telescope::increment('queries');
			Telescope::increment('query_time', $time);

			if ($slow) Telescope::increment('slow_queries');
			if ($occurrence === 2) Telescope::increment('duplicate_queries');

			if ($this->option('slow_only', false) && ! $slow) return;

			$content = [
				'connection' => $connection,
				'sql'        => Sanitizer::string($sql, 20000),
				'bindings'   => Sanitizer::value((array) $bindings),
				'time'       => round($time, 2),
				'slow'       => $slow,
				'occurrence' => $occurrence,
			];

			if ($this->option('backtrace', true))
			{
				$content['caller'] = Sql::caller();
			}

			$tags = [];

			if ($slow) $tags[] = 'slow';
			if ($occurrence > 1) $tags[] = 'duplicate';

			Telescope::record(EntryType::QUERY, IncomingEntry::make($content)->familyHash($hash)->tags($tags));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

}

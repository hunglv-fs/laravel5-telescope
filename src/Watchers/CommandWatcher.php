<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;
use HungLv\Telescope\Contracts\EntriesRepository;

/**
 * Records artisan runs. 5.0 fires `artisan.start` once per process, so the
 * entry is completed from a shutdown handler.
 */
class CommandWatcher extends Watcher {

	/**
	 * @var float|null
	 */
	protected $startedAt;

	/**
	 * @var string|null
	 */
	protected $batchId;

	/**
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$this->app = $app;

		$app['events']->listen('artisan.start', function() use ($watcher)
		{
			$watcher->start();
		});
	}

	/**
	 * Open the batch for this artisan process.
	 *
	 * @return void
	 */
	public function start()
	{
		$command = $this->commandName();

		foreach ((array) $this->option('ignore', []) as $pattern)
		{
			if (str_is($pattern, $command))
			{
				// Nothing from this command is worth storing; queue workers
				// switch recording back on for each job they process.
				Telescope::stopRecording();

				return;
			}
		}

		$this->startedAt = microtime(true);
		$this->batchId = Telescope::currentBatchId();

		$watcher = $this;

		register_shutdown_function(function() use ($watcher)
		{
			$watcher->finish();
		});
	}

	/**
	 * Write the command entry and flush whatever the command recorded.
	 *
	 * @return void
	 */
	public function finish()
	{
		if (is_null($this->startedAt)) return;

		try
		{
			Telescope::resumeForSummary();

			$fatal = error_get_last();
			$failed = $fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);

			$duration = round((microtime(true) - $this->startedAt) * 1000, 2);

			$content = [
				'command'   => $this->commandName(),
				'arguments' => $this->arguments(),
				'status'    => $failed ? 'failed' : 'finished',
				'duration'  => $duration,
				'memory'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
				'stats'     => Telescope::$stats,
			];

			if ($failed) $content['fatal'] = Sanitizer::value($fatal);

			$tags = [$this->commandName()];

			if ($failed) $tags[] = 'failed';

			// 5.0 gives no exit code here, so a reported exception is the only
			// signal that a cron'd command went wrong without dying outright.
			if (Telescope::stat('exceptions') > 0) $tags[] = 'exception';
			if ($duration >= (float) $this->option('slow', 5000)) $tags[] = 'slow';
			if (Telescope::stat('duplicate_queries') > 0) $tags[] = 'duplicate-queries';

			Telescope::record(EntryType::COMMAND, IncomingEntry::make($content)->familyHash(md5($this->commandName()))->tags($tags));

			Telescope::store($this->app->make('HungLv\Telescope\Contracts\EntriesRepository'));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * @return string
	 */
	protected function commandName()
	{
		$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];

		foreach (array_slice($argv, 1) as $argument)
		{
			if (substr($argument, 0, 1) !== '-') return $argument;
		}

		return 'list';
	}

	/**
	 * @return array
	 */
	protected function arguments()
	{
		$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];

		return array_values(array_slice($argv, 2));
	}

}

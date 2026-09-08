<?php namespace HungLv\Telescope\Queue;

use Exception;
use HungLv\Telescope\Telescope;
use Illuminate\Queue\Worker;
use Illuminate\Contracts\Queue\Job;
use HungLv\Telescope\Watchers\JobWatcher;

/**
 * The 5.0 queue worker, wrapped so each processed job becomes its own
 * Telescope batch: the job entry plus every query, log and cache call it made.
 */
class TelescopeWorker extends Worker {

	/**
	 * @var \HungLv\Telescope\Watchers\JobWatcher|null
	 */
	protected $telescopeWatcher;

	/**
	 * @param  \HungLv\Telescope\Watchers\JobWatcher  $watcher
	 * @return $this
	 */
	public function setTelescopeWatcher(JobWatcher $watcher)
	{
		$this->telescopeWatcher = $watcher;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function process($connection, Job $job, $maxTries = 0, $delay = 0)
	{
		if ( ! $this->telescopeWatcher || ! Telescope::config('enabled', false))
		{
			return parent::process($connection, $job, $maxTries, $delay);
		}

		Telescope::newBatch();
		Telescope::startRecording();

		$startedAt = microtime(true);

		try
		{
			$result = parent::process($connection, $job, $maxTries, $delay);
		}
		catch (Exception $e)
		{
			Telescope::resumeForSummary();

			$this->telescopeWatcher->recordJob($connection, $job, 'failed', $startedAt, $e);

			$this->flushTelescope();

			throw $e;
		}

		$status = (is_array($result) && ! empty($result['failed'])) ? 'failed' : 'processed';

		Telescope::resumeForSummary();

		$this->telescopeWatcher->recordJob($connection, $job, $status, $startedAt);

		$this->flushTelescope();

		return $result;
	}

	/**
	 * Persist after every job so a long-running worker shows up live.
	 *
	 * @return void
	 */
	protected function flushTelescope()
	{
		try
		{
			Telescope::store(app('HungLv\Telescope\Contracts\EntriesRepository'));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

}

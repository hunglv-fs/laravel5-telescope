<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Queue\TelescopeWorker;
use HungLv\Telescope\Support\Sanitizer;

/**
 * 5.0 fires no per-job events, so the queue worker itself is decorated.
 * Everything a job does — its queries above all — lands in the job's batch.
 */
class JobWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$app->extend('queue.worker', function($worker, $app) use ($watcher)
		{
			$replacement = new TelescopeWorker($app['queue'], $app['queue.failer'], $app['events']);

			$replacement->setTelescopeWatcher($watcher);

			return $replacement;
		});
	}

	/**
	 * A job finished, one way or another.
	 *
	 * @param  string  $connection
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  string  $status
	 * @param  float   $startedAt
	 * @param  \Exception|null  $exception
	 * @return void
	 */
	public function recordJob($connection, $job, $status, $startedAt, $exception = null)
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			$payload = $this->payload($job);
			$duration = round((microtime(true) - $startedAt) * 1000, 2);
			$name = $this->name($job, $payload);

			$content = [
				'name'       => $name,
				'connection' => $connection,
				'queue'      => method_exists($job, 'getQueue') ? $job->getQueue() : null,
				'status'     => $status,
				'attempts'   => method_exists($job, 'attempts') ? $job->attempts() : null,
				'duration'   => $duration,
				'memory'     => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
				'data'       => $this->data($payload),
				'stats'      => Telescope::$stats,
			];

			if ($exception)
			{
				$content['exception'] = [
					'class'   => get_class($exception),
					'message' => Sanitizer::string($exception->getMessage(), 4000),
					'file'    => $exception->getFile(),
					'line'    => $exception->getLine(),
				];
			}

			$tags = [$name, $status];

			if ($duration >= (float) $this->option('slow', 5000)) $tags[] = 'slow';
			if (Telescope::stat('duplicate_queries') > 0) $tags[] = 'duplicate-queries';

			Telescope::record(EntryType::JOB, IncomingEntry::make($content)->familyHash(md5($name))->tags($tags));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @return array
	 */
	protected function payload($job)
	{
		if ( ! method_exists($job, 'getRawBody')) return [];

		$payload = json_decode($job->getRawBody(), true);

		return is_array($payload) ? $payload : [];
	}

	/**
	 * Work out a readable job name, digging the class out of the serialized
	 * command 5.0 stores for queued command objects.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  array  $payload
	 * @return string
	 */
	protected function name($job, array $payload)
	{
		if (isset($payload['data']['commandName'])) return $payload['data']['commandName'];

		if (isset($payload['data']['command']) && is_string($payload['data']['command']))
		{
			if (preg_match('/^O:\d+:"([^"]+)"/', $payload['data']['command'], $matches))
			{
				return $matches[1];
			}
		}

		if (isset($payload['job'])) return $payload['job'];

		return method_exists($job, 'getName') ? $job->getName() : 'unknown';
	}

	/**
	 * @param  array  $payload
	 * @return array
	 */
	protected function data(array $payload)
	{
		$data = isset($payload['data']) ? $payload['data'] : [];

		if (isset($data['command']) && is_string($data['command']))
		{
			$data['command'] = Sanitizer::string($data['command'], 2000);
		}

		return Sanitizer::value($data);
	}

}

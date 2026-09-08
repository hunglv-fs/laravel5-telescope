<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

class LogWatcher extends Watcher {

	/**
	 * Monolog severities, weakest first.
	 *
	 * @var array
	 */
	protected static $levels = [
		'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency',
	];

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$app['events']->listen('illuminate.log', function($level, $message, $context) use ($watcher)
		{
			$watcher->record($level, $message, $context);
		});
	}

	/**
	 * @param  string  $level
	 * @param  mixed   $message
	 * @param  array   $context
	 * @return void
	 */
	public function record($level, $message, $context = [])
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			if ($this->belowThreshold($level)) return;

			$content = [
				'level'   => $level,
				'message' => Sanitizer::string((string) $message, 10000),
				'context' => Sanitizer::value((array) $context),
			];

			Telescope::record(EntryType::LOG, IncomingEntry::make($content)->tags([$level]));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * @param  string  $level
	 * @return bool
	 */
	protected function belowThreshold($level)
	{
		$minimum = array_search($this->option('level', 'debug'), static::$levels);
		$current = array_search($level, static::$levels);

		if ($minimum === false || $current === false) return false;

		return $current < $minimum;
	}

}

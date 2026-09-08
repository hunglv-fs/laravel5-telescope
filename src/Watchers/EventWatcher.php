<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

/**
 * Wildcard listener over the 5.0 string-based event dispatcher.
 */
class EventWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;
		$events = $app['events'];

		$events->listen('*', function() use ($watcher, $events)
		{
			$watcher->record($events->firing(), func_get_args());
		});
	}

	/**
	 * @param  string  $name
	 * @param  array   $payload
	 * @return void
	 */
	public function record($name, array $payload = [])
	{
		if ( ! Telescope::isRecording() || ! $name) return;

		try
		{
			foreach ((array) $this->option('ignore', []) as $pattern)
			{
				if (str_is($pattern, $name)) return;
			}

			$content = [
				'name'    => $name,
				'payload' => $this->option('payload', true) ? Sanitizer::value($payload) : null,
			];

			Telescope::record(EntryType::EVENT, IncomingEntry::make($content));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

}

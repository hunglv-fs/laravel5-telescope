<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

class CacheWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$app['events']->listen('cache.hit', function($key, $value) use ($watcher)
		{
			$watcher->record('hit', $key, $value);
		});

		$app['events']->listen('cache.missed', function($key) use ($watcher)
		{
			$watcher->record('missed', $key);
		});

		$app['events']->listen('cache.write', function($key, $value, $minutes = null) use ($watcher)
		{
			$watcher->record('set', $key, $value, $minutes);
		});

		$app['events']->listen('cache.delete', function($key) use ($watcher)
		{
			$watcher->record('forget', $key);
		});
	}

	/**
	 * @param  string  $action
	 * @param  string  $key
	 * @param  mixed   $value
	 * @param  int|null  $minutes
	 * @return void
	 */
	public function record($action, $key, $value = null, $minutes = null)
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			if ($action === 'hit') Telescope::increment('cache_hits');
			if ($action === 'missed') Telescope::increment('cache_misses');

			foreach ((array) $this->option('ignore', []) as $pattern)
			{
				if (str_is($pattern, $key)) return;
			}

			$content = [
				'type'    => $action,
				'key'     => Sanitizer::string((string) $key, 500),
				'expiration' => $minutes,
			];

			if (in_array($action, ['hit', 'set']) && $this->option('values', true))
			{
				$content['value'] = Sanitizer::value($value);
			}

			Telescope::record(EntryType::CACHE, IncomingEntry::make($content)->tags([$action]));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

}

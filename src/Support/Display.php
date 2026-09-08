<?php namespace HungLv\Telescope\Support;

use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\Storage\EntryResult;

/**
 * Presentation helpers for the Blade dashboard.
 */
class Display {

	/**
	 * A dashboard URL.
	 *
	 * @param  string  $path
	 * @param  array   $query
	 * @return string
	 */
	public static function url($path = '', array $query = [])
	{
		$url = url(Telescope::config('path', 'telescope').($path ? '/'.ltrim($path, '/') : ''));

		return $query ? $url.'?'.http_build_query($query) : $url;
	}

	/**
	 * The plural URL segment for an entry type.
	 *
	 * @param  string  $type
	 * @return string
	 */
	public static function segment($type)
	{
		$map = [
			EntryType::REQUEST   => 'requests',
			EntryType::COMMAND   => 'commands',
			EntryType::QUERY     => 'queries',
			EntryType::JOB       => 'jobs',
			EntryType::EXCEPTION => 'exceptions',
			EntryType::LOG       => 'logs',
			EntryType::CACHE     => 'cache',
			EntryType::MAIL      => 'mail',
			EntryType::EVENT     => 'events',
		];

		return isset($map[$type]) ? $map[$type] : $type;
	}

	/**
	 * Link to an entry's detail page.
	 *
	 * @param  \HungLv\Telescope\Storage\EntryResult  $entry
	 * @return string
	 */
	public static function link(EntryResult $entry)
	{
		return static::url(static::segment($entry->type).'/'.$entry->uuid);
	}

	/**
	 * The headline shown for an entry in list views.
	 *
	 * @param  \HungLv\Telescope\Storage\EntryResult  $entry
	 * @return string
	 */
	public static function label(EntryResult $entry)
	{
		switch ($entry->type)
		{
			case EntryType::REQUEST:
				return $entry->get('method').' '.$entry->get('uri');

			case EntryType::QUERY:
				return $entry->get('sql');

			case EntryType::JOB:
				return $entry->get('name');

			case EntryType::COMMAND:
				return trim($entry->get('command').' '.implode(' ', (array) $entry->get('arguments', [])));

			case EntryType::EXCEPTION:
				return $entry->get('class').': '.$entry->get('message');

			case EntryType::LOG:
				return $entry->get('message');

			case EntryType::CACHE:
				return strtoupper($entry->get('type')).' '.$entry->get('key');

			case EntryType::MAIL:
				return $entry->get('subject').' -> '.implode(', ', (array) $entry->get('to', []));

			case EntryType::EVENT:
				return $entry->get('name');
		}

		return $entry->type;
	}

	/**
	 * The secondary line under the headline.
	 *
	 * @param  \HungLv\Telescope\Storage\EntryResult  $entry
	 * @return string
	 */
	public static function subtitle(EntryResult $entry)
	{
		switch ($entry->type)
		{
			case EntryType::REQUEST:
				return $entry->get('controller_action');

			case EntryType::QUERY:
				$caller = $entry->get('caller');

				if (is_array($caller) && isset($caller['file']))
				{
					return static::relative($caller['file']).':'.$caller['line'];
				}

				return $entry->get('connection');

			case EntryType::JOB:
				return $entry->get('connection').' / '.$entry->get('queue');

			case EntryType::COMMAND:
				return $entry->get('status');

			case EntryType::EXCEPTION:
				return static::relative($entry->get('file')).':'.$entry->get('line');

			case EntryType::LOG:
				return $entry->get('level');
		}

		return '';
	}

	/**
	 * The right hand column: duration, status, whatever matters per type.
	 *
	 * @param  \HungLv\Telescope\Storage\EntryResult  $entry
	 * @return string
	 */
	public static function meta(EntryResult $entry)
	{
		switch ($entry->type)
		{
			case EntryType::QUERY:
				return static::duration($entry->get('time'));

			case EntryType::REQUEST:
				return $entry->get('response_status').' / '.static::duration($entry->get('duration'));

			case EntryType::JOB:
			case EntryType::COMMAND:
				return $entry->get('status').' / '.static::duration($entry->get('duration'));
		}

		return '';
	}

	/**
	 * Format milliseconds.
	 *
	 * @param  float|null  $ms
	 * @return string
	 */
	public static function duration($ms)
	{
		if (is_null($ms) || $ms === '') return '-';

		$ms = (float) $ms;

		if ($ms >= 1000) return round($ms / 1000, 2).' s';

		return round($ms, 2).' ms';
	}

	/**
	 * Strip the project root off a path.
	 *
	 * @param  string  $path
	 * @return string
	 */
	public static function relative($path)
	{
		$base = str_replace('\\', '/', base_path()).'/';

		$path = str_replace('\\', '/', (string) $path);

		return str_replace($base, '', $path);
	}

	/**
	 * Pretty print any payload for the detail view.
	 *
	 * @param  mixed  $value
	 * @return string
	 */
	public static function dump($value)
	{
		if (is_string($value)) return $value;

		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

		if (defined('JSON_PRETTY_PRINT')) $flags = $flags | JSON_PRETTY_PRINT;

		$json = json_encode($value, $flags);

		return $json === false ? var_export($value, true) : $json;
	}

	/**
	 * CSS class describing how alarming an entry is.
	 *
	 * @param  \HungLv\Telescope\Storage\EntryResult  $entry
	 * @return string
	 */
	public static function tone(EntryResult $entry)
	{
		if ($entry->type === EntryType::EXCEPTION) return 'danger';

		if ($entry->hasTag('failed') || $entry->hasTag('error')) return 'danger';

		if ($entry->hasTag('slow') || $entry->hasTag('duplicate')) return 'warning';

		if ($entry->type === EntryType::REQUEST && (int) $entry->get('response_status') >= 400) return 'danger';

		return '';
	}

	/**
	 * Human readable "3 minutes ago".
	 *
	 * @param  \Carbon\Carbon|null  $date
	 * @return string
	 */
	public static function ago($date)
	{
		return $date ? $date->diffForHumans() : '';
	}

}

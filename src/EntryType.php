<?php namespace HungLv\Telescope;

class EntryType {

	const REQUEST   = 'request';
	const COMMAND   = 'command';
	const QUERY     = 'query';
	const JOB       = 'job';
	const EXCEPTION = 'exception';
	const LOG       = 'log';
	const CACHE     = 'cache';
	const MAIL      = 'mail';
	const EVENT     = 'event';

	/**
	 * Every type, in the order they appear in the sidebar.
	 *
	 * @return array
	 */
	public static function all()
	{
		return [
			self::REQUEST, self::COMMAND, self::JOB, self::QUERY,
			self::EXCEPTION, self::LOG, self::CACHE, self::MAIL, self::EVENT,
		];
	}

	/**
	 * Human readable label for a type.
	 *
	 * @param  string  $type
	 * @return string
	 */
	public static function label($type)
	{
		$labels = [
			self::REQUEST   => 'Requests',
			self::COMMAND   => 'Commands',
			self::QUERY     => 'Queries',
			self::JOB       => 'Jobs',
			self::EXCEPTION => 'Exceptions',
			self::LOG       => 'Logs',
			self::CACHE     => 'Cache',
			self::MAIL      => 'Mail',
			self::EVENT     => 'Events',
		];

		return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
	}

}

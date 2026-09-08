<?php namespace HungLv\Telescope\Support;

class Sql {

	/**
	 * Collapse a statement into the "shape" that identifies it across runs,
	 * so `in (?, ?, ?)` and `in (?, ?)` count as the same query.
	 *
	 * @param  string  $sql
	 * @return string
	 */
	public static function shape($sql)
	{
		$sql = preg_replace('/\?(\s*,\s*\?)+/', '?', $sql);

		return trim(preg_replace('/\s+/', ' ', $sql));
	}

	/**
	 * A hash for the query shape, used as the entry family hash.
	 *
	 * @param  string  $sql
	 * @param  string  $connection
	 * @return string
	 */
	public static function hash($sql, $connection = '')
	{
		return md5($connection.'|'.static::shape($sql));
	}

	/**
	 * Inline the bindings so the statement can be pasted into a SQL client.
	 *
	 * @param  string  $sql
	 * @param  array   $bindings
	 * @return string
	 */
	public static function hydrate($sql, array $bindings)
	{
		foreach ($bindings as $binding)
		{
			if (is_null($binding)) $value = 'null';
			elseif (is_bool($binding)) $value = $binding ? '1' : '0';
			elseif (is_int($binding) || is_float($binding)) $value = (string) $binding;
			else $value = "'".str_replace("'", "''", (string) $binding)."'";

			$position = strpos($sql, '?');

			if ($position === false) break;

			$sql = substr_replace($sql, $value, $position, 1);
		}

		return $sql;
	}

	/**
	 * Find the application frame that issued the query.
	 *
	 * @param  int  $depth
	 * @return array|null
	 */
	public static function caller($depth = 40)
	{
		$frames = debug_backtrace(defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : 0, $depth);

		$package = static::packagePath();

		foreach ($frames as $frame)
		{
			if ( ! isset($frame['file'])) continue;

			$file = str_replace('\\', '/', $frame['file']);

			// Frames from the framework and from Telescope itself are noise;
			// the first frame left is the application code that ran the query.
			if (strpos($file, '/vendor/') !== false) continue;

			if ($package !== '' && strpos($file, $package) === 0) continue;

			return [
				'file' => $frame['file'],
				'line' => isset($frame['line']) ? $frame['line'] : null,
			];
		}

		return null;
	}

	/**
	 * Where this package lives, wherever it was installed.
	 *
	 * @return string
	 */
	protected static function packagePath()
	{
		// src/Support/Sql.php -> the package root.
		$root = dirname(dirname(__DIR__));

		return $root ? str_replace('\\', '/', $root).'/' : '';
	}

}

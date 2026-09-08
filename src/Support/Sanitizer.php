<?php namespace HungLv\Telescope\Support;

use DateTime;
use Exception;

/**
 * Turns arbitrary runtime values into something that survives json_encode()
 * and does not blow up the entries table.
 */
class Sanitizer {

	/**
	 * Recursively cast a value into a JSON-safe structure.
	 *
	 * @param  mixed  $value
	 * @param  int    $depth
	 * @return mixed
	 */
	public static function value($value, $depth = 0)
	{
		if ($depth > 8) return '[...]';

		if (is_array($value))
		{
			$clean = [];

			foreach ($value as $key => $item)
			{
				$clean[static::key($key)] = static::value($item, $depth + 1);
			}

			return $clean;
		}

		if ($value instanceof DateTime)
		{
			return $value->format('Y-m-d H:i:s');
		}

		if (is_object($value))
		{
			if (method_exists($value, 'toArray'))
			{
				try
				{
					return static::value($value->toArray(), $depth + 1);
				}
				catch (Exception $e)
				{
					// Fall through to the generic object handling below.
				}
			}

			if (method_exists($value, '__toString'))
			{
				return static::string((string) $value);
			}

			return '[object '.get_class($value).']';
		}

		if (is_resource($value)) return '[resource]';

		if (is_string($value)) return static::string($value);

		return $value;
	}

	/**
	 * Normalise an array key.
	 *
	 * @param  mixed  $key
	 * @return string|int
	 */
	protected static function key($key)
	{
		return is_int($key) ? $key : static::string((string) $key);
	}

	/**
	 * Make a string valid UTF-8 and bounded in length.
	 *
	 * @param  string  $value
	 * @param  int     $limit
	 * @return string
	 */
	public static function string($value, $limit = 4000)
	{
		if ( ! mb_check_encoding($value, 'UTF-8'))
		{
			return '[binary '.strlen($value).' bytes]';
		}

		if (mb_strlen($value, 'UTF-8') > $limit)
		{
			return mb_substr($value, 0, $limit, 'UTF-8').'... [truncated]';
		}

		return $value;
	}

	/**
	 * Replace the values of sensitive keys with a placeholder.
	 *
	 * @param  array  $input
	 * @param  array  $hidden
	 * @return array
	 */
	public static function hide(array $input, array $hidden)
	{
		foreach ($input as $key => $value)
		{
			if (in_array(strtolower($key), array_map('strtolower', $hidden)))
			{
				$input[$key] = '********';
			}
			elseif (is_array($value))
			{
				$input[$key] = static::hide($value, $hidden);
			}
		}

		return $input;
	}

	/**
	 * Encode a payload for storage, never throwing on bad data.
	 *
	 * @param  mixed  $content
	 * @return string
	 */
	public static function json($content)
	{
		$json = json_encode(static::value($content));

		if ($json === false)
		{
			// json_last_error_msg() is PHP 5.5+; Laravel 5.0 still runs on 5.4.
			$error = function_exists('json_last_error_msg')
						? json_last_error_msg()
						: 'json error '.json_last_error();

			$json = json_encode(['telescope_encoding_error' => $error]);
		}

		return $json;
	}

}

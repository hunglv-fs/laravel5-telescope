<?php namespace HungLv\Telescope\Support;

/**
 * Laravel 5.0 predates Str::uuid(), and ramsey/uuid is not part of the
 * 5.0 dependency tree, so Telescope ships its own RFC 4122 v4 generator.
 */
class Uuid {

	/**
	 * Generate a version 4 (random) UUID.
	 *
	 * @return string
	 */
	public static function v4()
	{
		$data = static::randomBytes(16);

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 * Grab the strongest source of randomness the runtime offers.
	 *
	 * @param  int  $length
	 * @return string
	 */
	protected static function randomBytes($length)
	{
		if (function_exists('random_bytes'))
		{
			return random_bytes($length);
		}

		if (function_exists('openssl_random_pseudo_bytes'))
		{
			return openssl_random_pseudo_bytes($length);
		}

		$bytes = '';

		for ($i = 0; $i < $length; $i++)
		{
			$bytes .= chr(mt_rand(0, 255));
		}

		return $bytes;
	}

}

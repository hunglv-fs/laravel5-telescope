<?php namespace HungLv\Telescope\Watchers;

abstract class Watcher {

	/**
	 * The watcher options taken from config/telescope.php.
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * @param  array  $options
	 */
	public function __construct(array $options = [])
	{
		$this->options = $options;
	}

	/**
	 * Hook the watcher into the application.
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 * @return void
	 */
	abstract public function register($app);

	/**
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	protected function option($key, $default = null)
	{
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}

}

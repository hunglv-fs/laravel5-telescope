<?php namespace HungLv\Telescope\Exceptions;

use Exception;
use HungLv\Telescope\Watchers\ExceptionWatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Wraps the application's own handler so Telescope sees every reported
 * exception without the app having to change App\Exceptions\Handler.
 */
class ExceptionHandlerDecorator implements ExceptionHandler {

	/**
	 * @var \Illuminate\Contracts\Debug\ExceptionHandler
	 */
	protected $handler;

	/**
	 * @var \HungLv\Telescope\Watchers\ExceptionWatcher
	 */
	protected $watcher;

	/**
	 * @param  \Illuminate\Contracts\Debug\ExceptionHandler  $handler
	 * @param  \HungLv\Telescope\Watchers\ExceptionWatcher  $watcher
	 */
	public function __construct(ExceptionHandler $handler, ExceptionWatcher $watcher)
	{
		$this->handler = $handler;
		$this->watcher = $watcher;
	}

	/**
	 * {@inheritdoc}
	 */
	public function report(Exception $e)
	{
		$this->watcher->recordException($e);

		return $this->handler->report($e);
	}

	/**
	 * {@inheritdoc}
	 */
	public function render($request, Exception $e)
	{
		return $this->handler->render($request, $e);
	}

	/**
	 * {@inheritdoc}
	 */
	public function renderForConsole($output, Exception $e)
	{
		return $this->handler->renderForConsole($output, $e);
	}

	/**
	 * Anything else the application's handler exposes.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return call_user_func_array([$this->handler, $method], $parameters);
	}

}

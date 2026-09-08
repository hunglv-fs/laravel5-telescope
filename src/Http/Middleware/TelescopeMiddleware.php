<?php namespace HungLv\Telescope\Http\Middleware;

use Closure;
use Exception;
use HungLv\Telescope\Telescope;
use Illuminate\Contracts\Routing\TerminableMiddleware;

/**
 * Opens a batch for the request and flushes it after the response was sent.
 * Registered as a singleton so handle() and terminate() share state.
 */
class TelescopeMiddleware implements TerminableMiddleware {

	/**
	 * @var float|null
	 */
	protected $startedAt;

	/**
	 * {@inheritdoc}
	 */
	public function handle($request, Closure $next)
	{
		try
		{
			if (Telescope::config('enabled', false) && ! $this->ignored($request))
			{
				$this->startedAt = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

				Telescope::newBatch();
				Telescope::startRecording();
			}
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}

		return $next($request);
	}

	/**
	 * {@inheritdoc}
	 */
	public function terminate($request, $response)
	{
		if (is_null($this->startedAt)) return;

		try
		{
			Telescope::resumeForSummary();

			if (Telescope::config('watchers.request.enabled', true))
			{
				app('HungLv\Telescope\Watchers\RequestWatcher')->record($request, $response, $this->startedAt);
			}

			Telescope::store(app('HungLv\Telescope\Contracts\EntriesRepository'));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * Telescope's own dashboard, and anything the config excludes.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return bool
	 */
	protected function ignored($request)
	{
		$path = ltrim($request->getPathInfo(), '/');

		$ignored = (array) Telescope::config('ignore_paths', []);

		$ignored[] = Telescope::config('path', 'telescope');
		$ignored[] = Telescope::config('path', 'telescope').'/*';

		foreach ($ignored as $pattern)
		{
			if ($pattern !== '' && str_is($pattern, $path)) return true;
		}

		return false;
	}

}

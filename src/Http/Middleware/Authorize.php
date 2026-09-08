<?php namespace HungLv\Telescope\Http\Middleware;

use Closure;
use HungLv\Telescope\Telescope;
use Illuminate\Contracts\Routing\Middleware;

/**
 * Guards the dashboard. Telescope stores request payloads and session data,
 * so this defaults to closed everywhere but the configured environments.
 */
class Authorize implements Middleware {

	/**
	 * {@inheritdoc}
	 */
	public function handle($request, Closure $next)
	{
		if ($this->authorized($request)) return $next($request);

		abort(403, 'Telescope dashboard is not available in this environment.');
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @return bool
	 */
	protected function authorized($request)
	{
		$environments = (array) Telescope::config('local_environments', ['local']);

		if (in_array(app()->environment(), $environments)) return true;

		if (in_array($request->ip(), (array) Telescope::config('allowed_ips', []))) return true;

		return Telescope::check($request);
	}

}

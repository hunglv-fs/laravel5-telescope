<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

/**
 * Driven by the terminable middleware rather than by an event, since 5.0
 * has no RequestHandled event.
 */
class RequestWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		// The middleware calls record() directly at terminate time.
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Symfony\Component\HttpFoundation\Response  $response
	 * @param  float  $startedAt
	 * @return void
	 */
	public function record($request, $response, $startedAt)
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			$status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
			$duration = round((microtime(true) - $startedAt) * 1000, 2);
			$hidden = (array) $this->option('hidden_parameters', ['password', 'password_confirmation', '_token']);

			$content = [
				'ip_address'        => $request->ip(),
				'method'            => $request->method(),
				'uri'               => '/'.ltrim($request->getPathInfo(), '/'),
				'controller_action' => $this->action($request),
				'response_status'   => $status,
				'duration'          => $duration,
				'memory'            => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
				'payload'           => Sanitizer::hide(Sanitizer::value($request->all()), $hidden),
				'headers'           => Sanitizer::hide($this->headers($request), (array) $this->option('hidden_headers', ['authorization', 'cookie', 'php-auth-pw'])),
				'session'           => $this->session($request),
				'user'              => $this->user(),
				'stats'             => Telescope::$stats,
			];

			if ($this->option('response_body', true))
			{
				$content['response'] = $this->body($response);
			}

			$tags = ['status:'.$status, $request->method()];

			if ($status >= 500) $tags[] = 'error';
			if ($duration >= (float) $this->option('slow', 1000)) $tags[] = 'slow';
			if (Telescope::stat('duplicate_queries') > 0) $tags[] = 'duplicate-queries';

			$family = md5($request->method().'|'.$this->action($request));

			Telescope::record(EntryType::REQUEST, IncomingEntry::make($content)->familyHash($family)->tags($tags));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @return string
	 */
	protected function action($request)
	{
		try
		{
			$route = $request->route();

			if ( ! $route) return 'Closure';

			$action = $route->getActionName();

			return $action ? $action : 'Closure';
		}
		catch (Exception $e)
		{
			return 'Closure';
		}
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @return array
	 */
	protected function headers($request)
	{
		$headers = [];

		foreach ($request->headers->all() as $key => $values)
		{
			$headers[$key] = is_array($values) ? implode(', ', $values) : $values;
		}

		return $headers;
	}

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @return array
	 */
	protected function session($request)
	{
		try
		{
			if ( ! $request->hasSession()) return [];

			return Sanitizer::value($request->session()->all());
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	/**
	 * @return array|null
	 */
	protected function user()
	{
		try
		{
			$user = app('auth')->user();

			if ( ! $user) return null;

			return [
				'id'    => $user->getAuthIdentifier(),
				'email' => isset($user->email) ? $user->email : null,
				'name'  => isset($user->name) ? $user->name : null,
			];
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	/**
	 * @param  \Symfony\Component\HttpFoundation\Response  $response
	 * @return mixed
	 */
	protected function body($response)
	{
		try
		{
			$content = $response->getContent();

			if (is_string($content) && strlen($content) > 0)
			{
				$type = $response->headers->get('Content-Type');

				if ($type && strpos($type, 'json') !== false)
				{
					$decoded = json_decode($content, true);

					if (is_array($decoded)) return Sanitizer::value($decoded);
				}

				if ($type && strpos($type, 'html') !== false)
				{
					return ['html' => strlen($content).' bytes'];
				}

				return Sanitizer::string($content, 2000);
			}
		}
		catch (Exception $e)
		{
			// Streamed and binary responses cannot be read here.
		}

		return null;
	}

}

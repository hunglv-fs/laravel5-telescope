<?php namespace HungLv\Telescope\Watchers;

use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;
use HungLv\Telescope\Exceptions\ExceptionHandlerDecorator;

class ExceptionWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		// 5.0 has no exception event, so the bound handler is decorated instead.
		$app->extend('Illuminate\Contracts\Debug\ExceptionHandler', function($handler) use ($watcher)
		{
			if ($handler instanceof ExceptionHandlerDecorator) return $handler;

			return new ExceptionHandlerDecorator($handler, $watcher);
		});
	}

	/**
	 * @param  \Exception  $e
	 * @param  array  $tags
	 * @return void
	 */
	public function recordException(Exception $e, array $tags = [])
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			Telescope::increment('exceptions');

			$content = [
				'class'   => get_class($e),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'message' => Sanitizer::string($e->getMessage(), 8000),
				'code'    => $e->getCode(),
				'trace'   => $this->trace($e),
			];

			$previous = $e->getPrevious();

			if ($previous)
			{
				$content['previous'] = [
					'class'   => get_class($previous),
					'message' => Sanitizer::string($previous->getMessage(), 2000),
					'file'    => $previous->getFile(),
					'line'    => $previous->getLine(),
				];
			}

			$family = md5(get_class($e).'|'.$e->getFile().'|'.$e->getLine());

			Telescope::record(EntryType::EXCEPTION, IncomingEntry::make($content)
				->familyHash($family)
				->tags(array_merge([get_class($e)], $tags)));
		}
		catch (Exception $inner)
		{
			Telescope::report($inner);
		}
	}

	/**
	 * @param  \Exception  $e
	 * @return array
	 */
	protected function trace(Exception $e)
	{
		$frames = [];

		foreach (array_slice($e->getTrace(), 0, (int) $this->option('trace_depth', 30)) as $frame)
		{
			$frames[] = [
				'file'     => isset($frame['file']) ? $frame['file'] : '[internal]',
				'line'     => isset($frame['line']) ? $frame['line'] : null,
				'function' => (isset($frame['class']) ? $frame['class'].$frame['type'] : '').(isset($frame['function']) ? $frame['function'] : ''),
			];
		}

		return $frames;
	}

}

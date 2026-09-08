<?php namespace HungLv\Telescope;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use HungLv\Telescope\Storage\DatabaseEntriesRepository;

class TelescopeServiceProvider extends ServiceProvider {

	/**
	 * Watcher class => config key under telescope.watchers.
	 *
	 * @var array
	 */
	protected $watchers = [
		'HungLv\Telescope\Watchers\QueryWatcher'     => 'query',
		'HungLv\Telescope\Watchers\RequestWatcher'   => 'request',
		'HungLv\Telescope\Watchers\CommandWatcher'   => 'command',
		'HungLv\Telescope\Watchers\JobWatcher'       => 'job',
		'HungLv\Telescope\Watchers\ExceptionWatcher' => 'exception',
		'HungLv\Telescope\Watchers\LogWatcher'       => 'log',
		'HungLv\Telescope\Watchers\CacheWatcher'     => 'cache',
		'HungLv\Telescope\Watchers\MailWatcher'      => 'mail',
		'HungLv\Telescope\Watchers\EventWatcher'     => 'event',
	];

	/**
	 * {@inheritdoc}
	 */
	public function register()
	{
		$this->mergeConfigFrom(__DIR__.'/../config/telescope.php', 'telescope');

		$this->registerStorage();

		$this->registerWatcherBindings();

		// Shared between handle() and terminate(), which the 5.0 kernel
		// resolves separately from the container.
		$this->app->singleton('HungLv\Telescope\Http\Middleware\TelescopeMiddleware');

		$this->commands([
			'HungLv\Telescope\Console\ClearCommand',
			'HungLv\Telescope\Console\PruneCommand',
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(Router $router)
	{
		$this->loadViewsFrom(__DIR__.'/../resources/views', 'telescope');

		$this->publishes([
			__DIR__.'/../config/telescope.php' => config_path('telescope.php'),
		], 'telescope-config');

		$this->publishes([
			__DIR__.'/../database/migrations' => base_path('database/migrations'),
		], 'telescope-migrations');

		$router->middleware('telescope', 'HungLv\Telescope\Http\Middleware\Authorize');

		if ( ! $this->app->routesAreCached())
		{
			require __DIR__.'/routes.php';
		}

		if ( ! $this->app['config']->get('telescope.enabled', false)) return;

		$this->registerWatchers();

		$this->registerRecorder();
	}

	/**
	 * Bind the entries repository.
	 *
	 * @return void
	 */
	protected function registerStorage()
	{
		$config = $this->app['config']->get('telescope.storage.database', []);

		// Resolved here, at boot, rather than when the repository is first
		// used: `migrate --database=other` (and anything else calling
		// setDefaultConnection) rewrites database.default at runtime, and
		// Telescope must keep writing where it was configured to write.
		$connection = isset($config['connection']) && $config['connection']
						? $config['connection']
						: $this->app['config']->get('database.default');

		$chunk = isset($config['chunk']) ? $config['chunk'] : 500;

		$this->app->singleton('HungLv\Telescope\Contracts\EntriesRepository', function($app) use ($connection, $chunk)
		{
			return new DatabaseEntriesRepository($app['db'], $connection, $chunk);
		});

		$this->app->singleton('HungLv\Telescope\Storage\DatabaseEntriesRepository', function($app)
		{
			return $app->make('HungLv\Telescope\Contracts\EntriesRepository');
		});
	}

	/**
	 * Bind every watcher as a singleton carrying its own options.
	 *
	 * @return void
	 */
	protected function registerWatcherBindings()
	{
		foreach ($this->watchers as $class => $key)
		{
			$this->app->singleton($class, function($app) use ($class, $key)
			{
				$options = $app['config']->get('telescope.watchers.'.$key, []);

				return new $class((array) $options);
			});
		}
	}

	/**
	 * Hook the enabled watchers into the framework.
	 *
	 * @return void
	 */
	protected function registerWatchers()
	{
		foreach ($this->watchers as $class => $key)
		{
			if ( ! $this->app['config']->get('telescope.watchers.'.$key.'.enabled', false)) continue;

			$this->app->make($class)->register($this->app);
		}
	}

	/**
	 * Open the recording lifecycle for the current runtime.
	 *
	 * @return void
	 */
	protected function registerRecorder()
	{
		if ($this->app->runningInConsole())
		{
			// The command watcher decides whether this run is worth keeping
			// and flushes the buffer from a shutdown handler.
			Telescope::newBatch();
			Telescope::startRecording();

			return;
		}

		$this->app->make('Illuminate\Contracts\Http\Kernel')
		          ->prependMiddleware('HungLv\Telescope\Http\Middleware\TelescopeMiddleware');
	}

}

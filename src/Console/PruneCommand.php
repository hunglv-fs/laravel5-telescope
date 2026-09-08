<?php namespace HungLv\Telescope\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use HungLv\Telescope\Contracts\EntriesRepository;

class PruneCommand extends Command {

	/**
	 * @var string
	 */
	protected $name = 'telescope:prune';

	/**
	 * @var string
	 */
	protected $description = 'Delete Telescope entries older than the given number of hours';

	/**
	 * @var \HungLv\Telescope\Contracts\EntriesRepository
	 */
	protected $entries;

	/**
	 * @param  \HungLv\Telescope\Contracts\EntriesRepository  $entries
	 */
	public function __construct(EntriesRepository $entries)
	{
		parent::__construct();

		$this->entries = $entries;
	}

	/**
	 * @return void
	 */
	public function fire()
	{
		$hours = (int) $this->option('hours');

		$deleted = $this->entries->prune(Carbon::now()->subHours($hours > 0 ? $hours : 48));

		$this->info($deleted.' entries older than '.$hours.' hours pruned.');
	}

	/**
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['hours', null, InputOption::VALUE_OPTIONAL, 'Delete entries recorded more than this many hours ago', 48],
		];
	}

}

<?php namespace HungLv\Telescope\Console;

use Illuminate\Console\Command;
use HungLv\Telescope\Contracts\EntriesRepository;

class ClearCommand extends Command {

	/**
	 * @var string
	 */
	protected $name = 'telescope:clear';

	/**
	 * @var string
	 */
	protected $description = 'Delete all Telescope entries';

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
		$this->entries->clear();

		$this->info('Telescope entries cleared.');
	}

}

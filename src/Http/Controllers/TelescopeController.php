<?php namespace HungLv\Telescope\Http\Controllers;

use Illuminate\Http\Request;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use Illuminate\Routing\Controller;
use HungLv\Telescope\Storage\EntryQueryOptions;
use HungLv\Telescope\Storage\DatabaseEntriesRepository;

class TelescopeController extends Controller {

	/**
	 * @var \HungLv\Telescope\Storage\DatabaseEntriesRepository
	 */
	protected $entries;

	/**
	 * @param  \HungLv\Telescope\Storage\DatabaseEntriesRepository  $entries
	 */
	public function __construct(DatabaseEntriesRepository $entries)
	{
		$this->entries = $entries;
	}

	/**
	 * Landing page: the request list.
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function index()
	{
		return redirect($this->url('requests'));
	}

	/**
	 * A paginated list of one entry type.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  string  $type
	 * @return \Illuminate\View\View
	 */
	public function entries(Request $request, $type)
	{
		$type = $this->normalizeType($type);

		$options = EntryQueryOptions::fromRequest($request);

		$entries = $this->entries->get($type, $options);

		$next = null;

		if (count($entries) === $options->limit)
		{
			$last = $entries[count($entries) - 1];

			// 5.0 has no fullUrlWithQuery(), so the cursor is appended by hand.
			$query = array_merge($request->query(), ['before' => $last->sequence]);

			$next = $request->url().'?'.http_build_query($query);
		}

		return view('telescope::list', [
			'type'    => $type,
			'entries' => $entries,
			'next'    => $next,
			'counts'  => $this->counts(),
			'filters' => $options,
		]);
	}

	/**
	 * A single entry plus everything else recorded in its batch.
	 *
	 * @param  string  $type
	 * @param  string  $uuid
	 * @return \Illuminate\View\View
	 */
	public function show($type, $uuid)
	{
		$entry = $this->entries->find($uuid);

		if ( ! $entry) abort(404);

		$batch = $this->entries->batch($entry->batchId);

		return view('telescope::show', [
			'type'   => $entry->type,
			'entry'  => $entry,
			'batch'  => $batch,
			'counts' => $this->counts(),
		]);
	}

	/**
	 * Everything recorded under one batch, oldest first.
	 *
	 * @param  string  $batchId
	 * @return \Illuminate\View\View
	 */
	public function batch($batchId)
	{
		$entries = $this->entries->batch($batchId);

		if (empty($entries)) abort(404);

		return view('telescope::batch', [
			'type'    => null,
			'batchId' => $batchId,
			'entries' => $entries,
			'counts'  => $this->counts(),
		]);
	}

	/**
	 * The optimisation view: N+1 candidates and the queries that cost most.
	 *
	 * @return \Illuminate\View\View
	 */
	public function insights()
	{
		$scan = (int) Telescope::config('insights.scan', 2000);

		return view('telescope::insights', [
			'type'       => null,
			'duplicates' => $this->entries->duplicateQueries((int) Telescope::config('insights.duplicate_threshold', 3), 25),
			'hotspots'   => $this->entries->queryHotspots($scan, 20),
			'requests'   => $this->entries->slowest(EntryType::REQUEST, 500, 10),
			'jobs'       => $this->entries->slowest(EntryType::JOB, 500, 10),
			'commands'   => $this->entries->slowest(EntryType::COMMAND, 200, 10),
			'counts'     => $this->counts(),
		]);
	}

	/**
	 * Empty the entries tables.
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function clear()
	{
		$this->entries->clear();

		return redirect($this->url('requests'));
	}

	/**
	 * Entry counts for the sidebar.
	 *
	 * @return array
	 */
	protected function counts()
	{
		return $this->entries->counts();
	}

	/**
	 * Map the plural segment used in URLs onto an entry type.
	 *
	 * @param  string  $type
	 * @return string
	 */
	protected function normalizeType($type)
	{
		$map = [
			'requests'   => EntryType::REQUEST,
			'commands'   => EntryType::COMMAND,
			'queries'    => EntryType::QUERY,
			'jobs'       => EntryType::JOB,
			'exceptions' => EntryType::EXCEPTION,
			'logs'       => EntryType::LOG,
			'cache'      => EntryType::CACHE,
			'mail'       => EntryType::MAIL,
			'events'     => EntryType::EVENT,
		];

		if ( ! isset($map[$type])) abort(404);

		return $map[$type];
	}

	/**
	 * @param  string  $path
	 * @return string
	 */
	protected function url($path)
	{
		return url(Telescope::config('path', 'telescope').'/'.$path);
	}

}

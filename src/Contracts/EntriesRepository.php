<?php namespace HungLv\Telescope\Contracts;

use DateTime;
use HungLv\Telescope\Storage\EntryQueryOptions;

interface EntriesRepository {

	/**
	 * Persist a set of buffered entries.
	 *
	 * @param  array  $entries
	 * @return void
	 */
	public function store(array $entries);

	/**
	 * Find a single entry by uuid.
	 *
	 * @param  string  $uuid
	 * @return \HungLv\Telescope\Storage\EntryResult|null
	 */
	public function find($uuid);

	/**
	 * List entries of a given type.
	 *
	 * @param  string|null  $type
	 * @param  \HungLv\Telescope\Storage\EntryQueryOptions  $options
	 * @return array
	 */
	public function get($type, EntryQueryOptions $options);

	/**
	 * Every entry belonging to a batch, oldest first.
	 *
	 * @param  string  $batchId
	 * @return array
	 */
	public function batch($batchId);

	/**
	 * Count entries per type.
	 *
	 * @return array
	 */
	public function counts();

	/**
	 * Delete entries recorded before the given moment.
	 *
	 * @param  \DateTime  $before
	 * @return int
	 */
	public function prune(DateTime $before);

	/**
	 * Delete everything.
	 *
	 * @return void
	 */
	public function clear();

}

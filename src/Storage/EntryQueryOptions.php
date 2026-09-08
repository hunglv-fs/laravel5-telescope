<?php namespace HungLv\Telescope\Storage;

class EntryQueryOptions {

	/**
	 * Only entries from this batch.
	 *
	 * @var string|null
	 */
	public $batchId;

	/**
	 * Only entries carrying this tag.
	 *
	 * @var string|null
	 */
	public $tag;

	/**
	 * Only entries sharing this family hash.
	 *
	 * @var string|null
	 */
	public $familyHash;

	/**
	 * Keyset pagination cursor: return entries below this sequence.
	 *
	 * @var int|null
	 */
	public $beforeSequence;

	/**
	 * How many entries to return.
	 *
	 * @var int
	 */
	public $limit = 50;

	/**
	 * Free text matched against the raw content column.
	 *
	 * @var string|null
	 */
	public $search;

	/**
	 * Build options from a request's query string.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return static
	 */
	public static function fromRequest($request)
	{
		$options = new static;

		$options->batchId = $request->input('batch') ?: null;
		$options->tag = $request->input('tag') ?: null;
		$options->familyHash = $request->input('family') ?: null;
		$options->search = $request->input('q') ?: null;
		$options->beforeSequence = $request->input('before') ?: null;

		$limit = (int) $request->input('limit', 50);

		$options->limit = $limit > 0 && $limit <= 200 ? $limit : 50;

		return $options;
	}

}

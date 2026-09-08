<?php namespace HungLv\Telescope;

use Carbon\Carbon;
use HungLv\Telescope\Support\Uuid;
use HungLv\Telescope\Support\Sanitizer;

class IncomingEntry {

	/**
	 * The entry's unique identifier.
	 *
	 * @var string
	 */
	public $uuid;

	/**
	 * The batch (request / command / job) this entry belongs to.
	 *
	 * @var string
	 */
	public $batchId;

	/**
	 * A hash grouping "the same thing" across batches, e.g. one SQL shape.
	 *
	 * @var string|null
	 */
	public $familyHash;

	/**
	 * The entry type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The entry payload.
	 *
	 * @var array
	 */
	public $content = [];

	/**
	 * The tags attached to the entry.
	 *
	 * @var array
	 */
	public $tags = [];

	/**
	 * Whether the entry shows up in list views.
	 *
	 * @var bool
	 */
	public $displayOnIndex = true;

	/**
	 * The moment the entry was recorded.
	 *
	 * @var \Carbon\Carbon
	 */
	public $recordedAt;

	/**
	 * @param  array  $content
	 */
	public function __construct(array $content = [])
	{
		$this->uuid = Uuid::v4();
		$this->batchId = Telescope::currentBatchId();
		$this->content = $content;
		$this->recordedAt = Carbon::now();
	}

	/**
	 * @param  array  $content
	 * @return static
	 */
	public static function make(array $content = [])
	{
		return new static($content);
	}

	/**
	 * @param  string  $type
	 * @return $this
	 */
	public function type($type)
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * @param  string  $hash
	 * @return $this
	 */
	public function familyHash($hash)
	{
		$this->familyHash = $hash;

		return $this;
	}

	/**
	 * @param  array  $tags
	 * @return $this
	 */
	public function tags(array $tags)
	{
		$merged = array_filter(array_merge($this->tags, $tags));

		// The tag column is varchar(100); long class names would otherwise
		// blow up the insert under MySQL's strict mode.
		foreach ($merged as $index => $tag)
		{
			$merged[$index] = mb_substr((string) $tag, 0, 100, 'UTF-8');
		}

		$this->tags = array_values(array_unique($merged));

		return $this;
	}

	/**
	 * @param  string  $tag
	 * @return bool
	 */
	public function hasTag($tag)
	{
		return in_array($tag, $this->tags);
	}

	/**
	 * Hide the entry from list views (it is still reachable from its batch).
	 *
	 * @return $this
	 */
	public function hideFromIndex()
	{
		$this->displayOnIndex = false;

		return $this;
	}

	/**
	 * The row shape expected by the telescope_entries table.
	 *
	 * @return array
	 */
	public function toDatabaseRow()
	{
		return [
			'uuid'                    => $this->uuid,
			'batch_id'                => $this->batchId,
			'family_hash'             => $this->familyHash,
			'should_display_on_index' => $this->displayOnIndex ? 1 : 0,
			'type'                    => $this->type,
			'content'                 => Sanitizer::json($this->content),
			'created_at'              => $this->recordedAt->format('Y-m-d H:i:s'),
		];
	}

}

<?php namespace HungLv\Telescope\Storage;

use Carbon\Carbon;

class EntryResult {

	public $sequence;
	public $uuid;
	public $batchId;
	public $familyHash;
	public $type;
	public $content = [];
	public $tags = [];
	public $createdAt;

	/**
	 * Hydrate from a database row.
	 *
	 * @param  object  $row
	 * @param  array   $tags
	 * @return static
	 */
	public static function fromRow($row, array $tags = [])
	{
		$result = new static;

		$result->sequence = $row->sequence;
		$result->uuid = $row->uuid;
		$result->batchId = $row->batch_id;
		$result->familyHash = $row->family_hash;
		$result->type = $row->type;
		$result->tags = $tags;
		$result->createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;

		$content = json_decode($row->content, true);

		$result->content = is_array($content) ? $content : [];

		return $result;
	}

	/**
	 * Read a content key with a default.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		return isset($this->content[$key]) ? $this->content[$key] : $default;
	}

	/**
	 * @param  string  $tag
	 * @return bool
	 */
	public function hasTag($tag)
	{
		return in_array($tag, $this->tags);
	}

}

<?php namespace HungLv\Telescope\Storage;

use DateTime;
use Exception;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use Illuminate\Database\DatabaseManager;
use HungLv\Telescope\Contracts\EntriesRepository;

class DatabaseEntriesRepository implements EntriesRepository {

	/**
	 * @var \Illuminate\Database\DatabaseManager
	 */
	protected $db;

	/**
	 * The connection Telescope writes to, or null for the default one.
	 *
	 * @var string|null
	 */
	protected $connection;

	/**
	 * How many rows go into a single insert.
	 *
	 * @var int
	 */
	protected $chunkSize;

	/**
	 * @param  \Illuminate\Database\DatabaseManager  $db
	 * @param  string|null  $connection
	 * @param  int  $chunkSize
	 */
	public function __construct(DatabaseManager $db, $connection = null, $chunkSize = 500)
	{
		$this->db = $db;
		$this->connection = $connection;
		$this->chunkSize = $chunkSize > 0 ? $chunkSize : 500;
	}

	/**
	 * A query builder for one of the Telescope tables.
	 *
	 * @param  string  $table
	 * @return \Illuminate\Database\Query\Builder
	 */
	public function table($table)
	{
		return $this->db->connection($this->connection)->table($table);
	}

	/**
	 * {@inheritdoc}
	 */
	public function store(array $entries)
	{
		if (empty($entries)) return;

		$rows = [];
		$tags = [];

		foreach ($entries as $entry)
		{
			$rows[] = $entry->toDatabaseRow();

			foreach ($entry->tags as $tag)
			{
				$tags[] = ['entry_uuid' => $entry->uuid, 'tag' => $tag];
			}
		}

		$self = $this;

		Telescope::withoutRecording(function() use ($self, $rows, $tags)
		{
			try
			{
				foreach (array_chunk($rows, $self->chunkSize()) as $chunk)
				{
					$self->table('telescope_entries')->insert($chunk);
				}

				foreach (array_chunk($tags, $self->chunkSize()) as $chunk)
				{
					$self->table('telescope_entries_tags')->insert($chunk);
				}
			}
			catch (Exception $e)
			{
				// A broken Telescope must never break the application it watches.
				Telescope::report($e);
			}
		});
	}

	/**
	 * @return int
	 */
	public function chunkSize()
	{
		return $this->chunkSize;
	}

	/**
	 * {@inheritdoc}
	 */
	public function find($uuid)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $uuid)
		{
			$row = $self->table('telescope_entries')->where('uuid', $uuid)->first();

			if ( ! $row) return null;

			return EntryResult::fromRow($row, $self->tagsFor([$uuid]));
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($type, EntryQueryOptions $options)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $type, $options)
		{
			$query = $self->table('telescope_entries')->select('telescope_entries.*');

			if ($type) $query->where('telescope_entries.type', $type);

			if ($options->batchId)
			{
				$query->where('telescope_entries.batch_id', $options->batchId);
			}
			else
			{
				$query->where('telescope_entries.should_display_on_index', 1);
			}

			if ($options->familyHash)
			{
				$query->where('telescope_entries.family_hash', $options->familyHash);
			}

			if ($options->tag)
			{
				$query->join('telescope_entries_tags', 'telescope_entries.uuid', '=', 'telescope_entries_tags.entry_uuid')
				      ->where('telescope_entries_tags.tag', $options->tag);
			}

			if ($options->search)
			{
				$query->where('telescope_entries.content', 'like', '%'.$options->search.'%');
			}

			if ($options->beforeSequence)
			{
				$query->where('telescope_entries.sequence', '<', $options->beforeSequence);
			}

			$rows = $query->orderBy('telescope_entries.sequence', 'desc')
			              ->limit($options->limit)
			              ->get();

			return $self->hydrate($rows);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function batch($batchId)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $batchId)
		{
			$rows = $self->table('telescope_entries')
			             ->where('batch_id', $batchId)
			             ->orderBy('sequence', 'asc')
			             ->get();

			return $self->hydrate($rows);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function counts()
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self)
		{
			$counts = [];

			$rows = $self->table('telescope_entries')
			             ->select($self->raw('type, count(*) as aggregate'))
			             ->groupBy('type')
			             ->get();

			foreach ($rows as $row)
			{
				$row = (array) $row;

				$counts[$row['type']] = (int) $row['aggregate'];
			}

			return $counts;
		});
	}

	/**
	 * Query shapes that repeat inside a single batch: the N+1 detector.
	 *
	 * @param  int  $threshold
	 * @param  int  $limit
	 * @return array
	 */
	public function duplicateQueries($threshold = 3, $limit = 25)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $threshold, $limit)
		{
			$rows = $self->table('telescope_entries')
			             ->select($self->raw('batch_id, family_hash, count(*) as occurrences, max(sequence) as sequence'))
			             ->where('type', EntryType::QUERY)
			             ->groupBy('batch_id', 'family_hash')
			             ->havingRaw('count(*) >= ?', [$threshold])
			             ->orderBy('sequence', 'desc')
			             ->limit($limit)
			             ->get();

			$results = [];

			foreach ($rows as $row)
			{
				$row = (array) $row;

				$sample = $self->table('telescope_entries')
				               ->where('sequence', $row['sequence'])
				               ->first();

				$results[] = [
					'batch_id'    => $row['batch_id'],
					'family_hash' => $row['family_hash'],
					'occurrences' => (int) $row['occurrences'],
					'entry'       => $sample ? EntryResult::fromRow($sample) : null,
				];
			}

			return $results;
		});
	}

	/**
	 * Aggregate recent queries by shape: total time, worst case, call count.
	 *
	 * @param  int  $scan   how many recent query entries to inspect
	 * @param  int  $limit  how many shapes to return
	 * @return array
	 */
	public function queryHotspots($scan = 2000, $limit = 20)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $scan, $limit)
		{
			$rows = $self->table('telescope_entries')
			             ->where('type', EntryType::QUERY)
			             ->orderBy('sequence', 'desc')
			             ->limit($scan)
			             ->get();

			$shapes = [];

			foreach ($rows as $row)
			{
				$entry = EntryResult::fromRow($row);

				$hash = $entry->familyHash;

				if ( ! isset($shapes[$hash]))
				{
					$shapes[$hash] = [
						'family_hash' => $hash,
						'sql'         => $entry->get('sql'),
						'connection'  => $entry->get('connection'),
						'calls'       => 0,
						'total_time'  => 0.0,
						'max_time'    => 0.0,
						'uuid'        => $entry->uuid,
					];
				}

				$time = (float) $entry->get('time', 0);

				$shapes[$hash]['calls']++;
				$shapes[$hash]['total_time'] += $time;
				$shapes[$hash]['max_time'] = max($shapes[$hash]['max_time'], $time);
			}

			uasort($shapes, function($a, $b)
			{
				if ($a['total_time'] == $b['total_time']) return 0;

				return $a['total_time'] < $b['total_time'] ? 1 : -1;
			});

			return array_slice(array_values($shapes), 0, $limit);
		});
	}

	/**
	 * The slowest recent entries of a given type, sorted in PHP because the
	 * duration lives inside the JSON payload.
	 *
	 * @param  string  $type
	 * @param  int     $scan
	 * @param  int     $limit
	 * @return array
	 */
	public function slowest($type, $scan = 500, $limit = 10)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $type, $scan, $limit)
		{
			$rows = $self->table('telescope_entries')
			             ->where('type', $type)
			             ->where('should_display_on_index', 1)
			             ->orderBy('sequence', 'desc')
			             ->limit($scan)
			             ->get();

			$entries = $self->hydrate($rows);

			usort($entries, function($a, $b)
			{
				$left = (float) $a->get('duration', 0);
				$right = (float) $b->get('duration', 0);

				if ($left == $right) return 0;

				return $left < $right ? 1 : -1;
			});

			return array_slice($entries, 0, $limit);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function prune(DateTime $before)
	{
		$self = $this;

		return Telescope::withoutRecording(function() use ($self, $before)
		{
			$cutoff = $before->format('Y-m-d H:i:s');
			$deleted = 0;

			do
			{
				$uuids = [];

				$rows = $self->table('telescope_entries')
				             ->where('created_at', '<', $cutoff)
				             ->limit(1000)
				             ->get();

				foreach ($rows as $row)
				{
					$row = (array) $row;

					$uuids[] = $row['uuid'];
				}

				if (empty($uuids)) break;

				$self->table('telescope_entries_tags')->whereIn('entry_uuid', $uuids)->delete();

				$deleted += $self->table('telescope_entries')->whereIn('uuid', $uuids)->delete();
			}
			while (count($uuids) === 1000);

			return $deleted;
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear()
	{
		$self = $this;

		Telescope::withoutRecording(function() use ($self)
		{
			$self->table('telescope_entries_tags')->delete();
			$self->table('telescope_entries')->delete();
		});
	}

	/**
	 * Turn rows into entry results, loading their tags in one extra query.
	 *
	 * @param  array  $rows
	 * @return array
	 */
	public function hydrate($rows)
	{
		$rows = is_array($rows) ? $rows : $rows->all();

		if (empty($rows)) return [];

		$uuids = [];

		foreach ($rows as $row)
		{
			$uuids[] = $row->uuid;
		}

		$tags = $this->tagsFor($uuids, true);

		$entries = [];

		foreach ($rows as $row)
		{
			$entries[] = EntryResult::fromRow($row, isset($tags[$row->uuid]) ? $tags[$row->uuid] : []);
		}

		return $entries;
	}

	/**
	 * Load tags for a set of entries.
	 *
	 * @param  array  $uuids
	 * @param  bool   $grouped
	 * @return array
	 */
	public function tagsFor(array $uuids, $grouped = false)
	{
		if (empty($uuids)) return [];

		$rows = $this->table('telescope_entries_tags')->whereIn('entry_uuid', $uuids)->get();

		$tags = [];

		foreach ($rows as $row)
		{
			$row = (array) $row;

			if ($grouped)
			{
				$tags[$row['entry_uuid']][] = $row['tag'];
			}
			else
			{
				$tags[] = $row['tag'];
			}
		}

		return $tags;
	}

	/**
	 * A raw expression on Telescope's connection.
	 *
	 * @param  string  $value
	 * @return \Illuminate\Database\Query\Expression
	 */
	public function raw($value)
	{
		return $this->db->connection($this->connection)->raw($value);
	}

}

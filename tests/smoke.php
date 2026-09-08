<?php
/**
 * Standalone check of the Telescope recording pipeline.
 *
 * Runs on any PHP 5.5+ without booting Laravel 5.0: the handful of framework
 * helpers the package touches are stubbed below. Run it with:
 *
 *     php packages/telescope/tests/smoke.php
 */

namespace Carbon {
    class Carbon {
        public $ts;
        public function __construct($ts = null) { $this->ts = $ts ?: time(); }
        public static function now() { return new static; }
        public static function parse($v) { return new static(is_numeric($v) ? $v : strtotime($v)); }
        public function format($f) { return date($f, $this->ts); }
        public function diffForHumans() { return (time() - $this->ts).' seconds ago'; }
        public function subHours($h) { return new static($this->ts - $h * 3600); }
    }
}

namespace {

    class ConfigStub {
        protected $items;
        public function __construct(array $items) { $this->items = $items; }
        public function get($key, $default = null) {
            $value = $this->items;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
                $value = $value[$segment];
            }
            return $value;
        }
    }

    $GLOBALS['__base'] = realpath(__DIR__.'/../../..');

    $GLOBALS['__config'] = new ConfigStub(['telescope' => require __DIR__.'/../config/telescope.php']);

    function env($key, $default = null) { return $default; }
    function app($abstract = null) { if ($abstract === 'config') return $GLOBALS['__config']; throw new Exception("unbound: $abstract"); }
    function str_is($pattern, $value) {
        if ($pattern == $value) return true;
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return (bool) preg_match('#^'.$pattern.'\z#', $value);
    }
    function base_path($path = '') { return $GLOBALS['__base'].($path ? '/'.$path : ''); }

    require __DIR__.'/../autoload.php';

    use HungLv\Telescope\Telescope;
    use HungLv\Telescope\EntryType;
    use HungLv\Telescope\IncomingEntry;
    use HungLv\Telescope\Support\Sql;
    use HungLv\Telescope\Support\Uuid;
    use HungLv\Telescope\Support\Display;
    use HungLv\Telescope\Support\Sanitizer;
    use HungLv\Telescope\Watchers\QueryWatcher;
    use HungLv\Telescope\Watchers\LogWatcher;
    use HungLv\Telescope\Watchers\CacheWatcher;
    use HungLv\Telescope\Watchers\ExceptionWatcher;

    $pass = 0; $fail = 0;
    function check($label, $condition, $detail = '') {
        global $pass, $fail;
        if ($condition) { $pass++; echo "  ok   $label\n"; }
        else { $fail++; echo "  FAIL $label $detail\n"; }
    }

    echo "\n== uuid ==\n";
    $uuid = Uuid::v4();
    check('rfc4122 v4 shape', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid), $uuid);
    check('unique', Uuid::v4() !== Uuid::v4());

    echo "\n== sql shaping ==\n";
    $a = 'select * from users where id in (?, ?, ?) and x = ?';
    $b = 'select  *  from users where id in (?, ?) and x = ?';
    check('in-lists collapse to one shape', Sql::hash($a, 'mysql') === Sql::hash($b, 'mysql'), Sql::shape($a).' vs '.Sql::shape($b));
    check('connection separates shapes', Sql::hash($a, 'mysql') !== Sql::hash($a, 'other'));
    check('bindings inlined', Sql::hydrate('select * from t where a = ? and b = ? and c = ?', ["O'Brien", 5, null])
        === "select * from t where a = 'O''Brien' and b = 5 and c = null");

    echo "\n== sanitizer ==\n";
    check('binary flagged', strpos(Sanitizer::string("\xB1\x31\xFF"), '[binary') === 0);
    check('long strings truncated', strlen(Sanitizer::string(str_repeat('a', 9000))) < 5000);
    check('passwords hidden', Sanitizer::hide(['Password' => 'secret', 'a' => ['password' => 'x']], ['password'])
        === ['Password' => '********', 'a' => ['password' => '********']]);
    check('objects survive encoding', Sanitizer::json(['d' => new \Carbon\Carbon, 'r' => fopen('php://memory', 'r')]) !== false);

    echo "\n== recording pipeline ==\n";
    Telescope::newBatch();
    Telescope::startRecording();
    $batch = Telescope::currentBatchId();

    $queries = new QueryWatcher(['slow' => 100, 'backtrace' => true]);
    $queries->recordQuery('select * from products where id = ?', [7], 12.5, 'mysql');
    $queries->recordQuery('select * from products where id = ?', [8], 3.0, 'mysql');
    $queries->recordQuery('select * from orders where total > ?', [10], 450.0, 'mysql');

    (new LogWatcher(['level' => 'debug']))->record('error', 'boom', ['k' => 'v']);
    (new CacheWatcher(['values' => true, 'ignore' => ['skip:*']]))->record('hit', 'user:1', ['id' => 1]);
    (new CacheWatcher(['ignore' => ['skip:*']]))->record('missed', 'skip:me');
    (new ExceptionWatcher(['trace_depth' => 5]))->recordException(new RuntimeException('exploded'));

    $entries = Telescope::$entriesQueue;
    check('entries buffered', count($entries) === 6, count($entries).' entries');
    check('ignored cache key skipped', count(array_filter($entries, function($e) { return $e->type === EntryType::CACHE; })) === 1);
    check('all share the batch', count(array_unique(array_map(function($e) { return $e->batchId; }, $entries))) === 1);

    $q = array_values(array_filter($entries, function($e) { return $e->type === EntryType::QUERY; }));
    check('duplicate tagged on 2nd run', $q[1]->hasTag('duplicate'), implode(',', $q[1]->tags));
    check('1st run not tagged duplicate', ! $q[0]->hasTag('duplicate'));
    check('slow query tagged', $q[2]->hasTag('slow'), implode(',', $q[2]->tags));
    check('same shape shares family hash', $q[0]->familyHash === $q[1]->familyHash);
    check('frames inside the package are skipped', $q[0]->content['caller'] === null,
        json_encode($q[0]->content['caller']));

    echo "\n== stats ==\n";
    check('query count', Telescope::stat('queries') === 3, Telescope::stat('queries'));
    check('slow count', Telescope::stat('slow_queries') === 1);
    check('duplicate shapes', Telescope::stat('duplicate_queries') === 1);
    check('cache hit/miss', Telescope::stat('cache_hits') === 1 && Telescope::stat('cache_misses') === 1);
    check('exception counted', Telescope::stat('exceptions') === 1);

    echo "\n== storage row ==\n";
    $row = $q[0]->toDatabaseRow();
    check('row columns', array_keys($row) === ['uuid','batch_id','family_hash','should_display_on_index','type','content','created_at'], implode(',', array_keys($row)));
    check('content is json', is_array(json_decode($row['content'], true)));
    check('family hash fits char(32)', strlen($row['family_hash']) === 32);
    check('created_at is a datetime', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']));

    $long = IncomingEntry::make([])->type('job')->tags([str_repeat('A', 250)]);
    check('tags truncated to 100 chars', strlen($long->tags[0]) === 100, strlen($long->tags[0]));

    echo "\n== flush ==\n";
    class MemoryRepository implements \HungLv\Telescope\Contracts\EntriesRepository {
        public $stored = [];
        public function store(array $entries) { $this->stored = array_merge($this->stored, $entries); }
        public function find($uuid) { return null; }
        public function get($type, \HungLv\Telescope\Storage\EntryQueryOptions $options) { return []; }
        public function batch($batchId) { return []; }
        public function counts() { return []; }
        public function prune(\DateTime $before) { return 0; }
        public function clear() {}
    }
    $repo = new MemoryRepository;
    Telescope::store($repo);
    check('buffer flushed to repository', count($repo->stored) === 6 && count(Telescope::$entriesQueue) === 0);

    echo "\n== recording switches ==\n";
    Telescope::withoutRecording(function() use ($queries) { $queries->recordQuery('select 1', [], 1, 'mysql'); });
    check('withoutRecording suppresses', count(Telescope::$entriesQueue) === 0);
    Telescope::stopRecording();
    $queries->recordQuery('select 2', [], 1, 'mysql');
    check('stopRecording suppresses', count(Telescope::$entriesQueue) === 0);
    Telescope::startRecording();
    for ($i = 0; $i < 400; $i++) $queries->recordQuery('select '.$i, [], 1, 'mysql');
    check('per-batch limit enforced', count(Telescope::$entriesQueue) === 300, count(Telescope::$entriesQueue));

    echo "\n== summary entry survives the cap ==\n";
    check('capped batch stops recording', ! Telescope::isRecording());
    Telescope::resumeForSummary();
    $before = count(Telescope::$entriesQueue);
    Telescope::record(EntryType::REQUEST, IncomingEntry::make(['uri' => '/heavy', 'stats' => Telescope::$stats]));
    check('summary entry still recorded past the cap', count(Telescope::$entriesQueue) === $before + 1,
        $before.' -> '.count(Telescope::$entriesQueue));
    check('cap re-arms straight after', ! Telescope::isRecording());

    echo "\n== display ==\n";
    check('ms formatting', Display::duration(12.345) === '12.35 ms', Display::duration(12.345));
    check('seconds formatting', Display::duration(1500) === '1.5 s', Display::duration(1500));
    check('null duration', Display::duration(null) === '-');
    check('paths relativised', Display::relative(base_path('app/Http/Kernel.php')) === 'app/Http/Kernel.php', Display::relative(base_path('app/Http/Kernel.php')));
    check('segments map back', Display::segment(EntryType::QUERY) === 'queries' && Display::segment(EntryType::CACHE) === 'cache');
    check('every type has a label + segment', count(array_unique(array_map(function($t) { return Display::segment($t); }, EntryType::all()))) === count(EntryType::all()));

    echo "\n== query backtrace points at app code ==\n";
    Telescope::newBatch();
    Telescope::startRecording();

    // Called from a file outside packages/telescope, i.e. what application
    // code looks like to the backtrace filter.
    $appFile = sys_get_temp_dir().'/telescope_fake_app_'.getmypid().'.php';
    file_put_contents($appFile, '<?php function telescope_fake_app_query($w) { $w->recordQuery("select * from fake where id = ?", array(1), 5.0, "mysql"); }');
    require $appFile;

    telescope_fake_app_query($queries);

    $recorded = Telescope::$entriesQueue[0];
    $caller = $recorded->content['caller'];

    // realpath(): macOS reports /private/var for /var/folders temp paths.
    check('caller file is the calling code', is_array($caller) && $caller['file'] === realpath($appFile), json_encode($caller));
    check('caller line recorded', is_array($caller) && $caller['line'] === 1, json_encode($caller));

    unlink($appFile);

    echo "\n".($fail === 0 ? "ALL $pass CHECKS PASSED\n" : "$pass passed, $fail FAILED\n");
    exit($fail === 0 ? 0 : 1);
}

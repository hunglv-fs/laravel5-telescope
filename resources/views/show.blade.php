<?php $batchId = $entry->batchId; ?>
@extends('telescope::layout')

@section('content')
	<h1>{{ \HungLv\Telescope\EntryType::label($entry->type) }}</h1>
	<div class="muted">
		{{ $entry->createdAt ? $entry->createdAt->format('Y-m-d H:i:s') : '' }} &middot;
		{{ \HungLv\Telescope\Support\Display::ago($entry->createdAt) }} &middot;
		<a href="{{ \HungLv\Telescope\Support\Display::url('batch/'.$entry->batchId) }}">batch {{ substr($entry->batchId, 0, 8) }}</a>
	</div>

	<div class="toolbar">
		@foreach ($entry->tags as $tag)
			<a class="tag {{ $tag }}" href="{{ \HungLv\Telescope\Support\Display::url(\HungLv\Telescope\Support\Display::segment($entry->type), ['tag' => $tag]) }}">{{ $tag }}</a>
		@endforeach
	</div>

	@if ($entry->type === 'query')
		<div class="panel">
			<h3>Statement</h3>
			<pre>{{ $entry->get('sql') }}</pre>
			<h3>With bindings inlined</h3>
			<pre>{{ \HungLv\Telescope\Support\Sql::hydrate($entry->get('sql'), (array) $entry->get('bindings', [])) }}</pre>
			<dl>
				<dt>Duration</dt><dd>{{ \HungLv\Telescope\Support\Display::duration($entry->get('time')) }}</dd>
				<dt>Connection</dt><dd>{{ $entry->get('connection') }}</dd>
				<dt>Occurrence in batch</dt><dd>#{{ $entry->get('occurrence') }}</dd>
				<?php $caller = $entry->get('caller'); ?>
				@if (is_array($caller))
					<dt>Called from</dt><dd class="mono">{{ \HungLv\Telescope\Support\Display::relative($caller['file']) }}:{{ $caller['line'] }}</dd>
				@endif
				<dt>Same shape</dt>
				<dd><a href="{{ \HungLv\Telescope\Support\Display::url('queries', ['family' => $entry->familyHash]) }}">every run of this query</a></dd>
			</dl>
		</div>
	@endif

	@if ($entry->type === 'request')
		<div class="panel">
			<h3>Request</h3>
			<dl>
				<dt>Method &amp; URI</dt><dd class="mono">{{ $entry->get('method') }} {{ $entry->get('uri') }}</dd>
				<dt>Action</dt><dd class="mono">{{ $entry->get('controller_action') }}</dd>
				<dt>Status</dt><dd>{{ $entry->get('response_status') }}</dd>
				<dt>Duration</dt><dd>{{ \HungLv\Telescope\Support\Display::duration($entry->get('duration')) }}</dd>
				<dt>Peak memory</dt><dd>{{ $entry->get('memory') }} MB</dd>
				<dt>IP</dt><dd>{{ $entry->get('ip_address') }}</dd>
			</dl>
		</div>
	@endif

	@if ($entry->type === 'job')
		<div class="panel">
			<h3>Job</h3>
			<dl>
				<dt>Name</dt><dd class="mono">{{ $entry->get('name') }}</dd>
				<dt>Status</dt><dd>{{ $entry->get('status') }}</dd>
				<dt>Connection / queue</dt><dd>{{ $entry->get('connection') }} / {{ $entry->get('queue') }}</dd>
				<dt>Attempts</dt><dd>{{ $entry->get('attempts') }}</dd>
				<dt>Duration</dt><dd>{{ \HungLv\Telescope\Support\Display::duration($entry->get('duration')) }}</dd>
				<dt>Peak memory</dt><dd>{{ $entry->get('memory') }} MB</dd>
			</dl>
		</div>
	@endif

	@if ($entry->type === 'command')
		<div class="panel">
			<h3>Command</h3>
			<dl>
				<dt>Command</dt><dd class="mono">{{ $entry->get('command') }}</dd>
				<dt>Arguments</dt><dd class="mono">{{ implode(' ', (array) $entry->get('arguments', [])) }}</dd>
				<dt>Status</dt><dd>{{ $entry->get('status') }}</dd>
				<dt>Duration</dt><dd>{{ \HungLv\Telescope\Support\Display::duration($entry->get('duration')) }}</dd>
				<dt>Peak memory</dt><dd>{{ $entry->get('memory') }} MB</dd>
			</dl>
		</div>
	@endif

	@if ($entry->type === 'exception')
		<div class="panel">
			<h3>{{ $entry->get('class') }}</h3>
			<pre>{{ $entry->get('message') }}</pre>
			<dl>
				<dt>Location</dt><dd class="mono">{{ \HungLv\Telescope\Support\Display::relative($entry->get('file')) }}:{{ $entry->get('line') }}</dd>
			</dl>
			<h3>Stack trace</h3>
			<pre><?php foreach ((array) $entry->get('trace', []) as $i => $frame): ?>
#{{ $i }} {{ \HungLv\Telescope\Support\Display::relative($frame['file']) }}:{{ $frame['line'] }} {{ $frame['function'] }}
<?php endforeach; ?></pre>
		</div>
	@endif

	<?php $stats = $entry->get('stats'); ?>
	@if (is_array($stats))
		<div class="panel">
			<h3>What this batch cost</h3>
			<div class="stats">
				<div class="stat"><b>{{ isset($stats['queries']) ? $stats['queries'] : 0 }}</b><span>queries</span></div>
				<div class="stat"><b>{{ \HungLv\Telescope\Support\Display::duration(isset($stats['query_time']) ? $stats['query_time'] : 0) }}</b><span>in SQL</span></div>
				<div class="stat"><b>{{ isset($stats['slow_queries']) ? $stats['slow_queries'] : 0 }}</b><span>slow</span></div>
				<div class="stat"><b>{{ isset($stats['duplicate_queries']) ? $stats['duplicate_queries'] : 0 }}</b><span>duplicated shapes</span></div>
				<div class="stat"><b>{{ isset($stats['cache_hits']) ? $stats['cache_hits'] : 0 }}/{{ isset($stats['cache_misses']) ? $stats['cache_misses'] : 0 }}</b><span>cache hit/miss</span></div>
				<div class="stat"><b>{{ isset($stats['exceptions']) ? $stats['exceptions'] : 0 }}</b><span>exceptions</span></div>
			</div>
		</div>
	@endif

	<div class="panel">
		<h3>Full payload</h3>
		<pre>{{ \HungLv\Telescope\Support\Display::dump($entry->content) }}</pre>
	</div>

	@if (count($batch) > 1)
		@include('telescope::_timeline')
	@endif
@stop

@extends('telescope::layout')

@section('content')
	<h1>{{ \HungLv\Telescope\EntryType::label($type) }}</h1>
	<div class="muted">Newest first. {{ count($entries) }} entries shown.</div>

	<form class="toolbar" method="GET" action="{{ \HungLv\Telescope\Support\Display::url(\HungLv\Telescope\Support\Display::segment($type)) }}">
		<input type="text" name="q" value="{{ $filters->search }}" placeholder="Search entry content...">
		@if ($filters->tag)
			<input type="hidden" name="tag" value="{{ $filters->tag }}">
		@endif
		<button type="submit">Filter</button>
		@if ($filters->search || $filters->tag || $filters->familyHash || $filters->batchId)
			<a class="btn" href="{{ \HungLv\Telescope\Support\Display::url(\HungLv\Telescope\Support\Display::segment($type)) }}">Reset</a>
		@endif
		@if ($type === 'query')
			<a class="btn" href="{{ \HungLv\Telescope\Support\Display::url('queries', ['tag' => 'slow']) }}">Slow only</a>
			<a class="btn" href="{{ \HungLv\Telescope\Support\Display::url('queries', ['tag' => 'duplicate']) }}">Duplicates only</a>
		@endif
	</form>

	@if ($filters->tag || $filters->familyHash || $filters->batchId)
		<div class="toolbar">
			@if ($filters->tag)<span class="tag">tag: {{ $filters->tag }}</span>@endif
			@if ($filters->familyHash)<span class="tag">shape: {{ substr($filters->familyHash, 0, 8) }}</span>@endif
			@if ($filters->batchId)<span class="tag">batch: {{ substr($filters->batchId, 0, 8) }}</span>@endif
		</div>
	@endif

	@if (count($entries) === 0)
		<div class="empty">Nothing recorded yet.</div>
	@else
		<table>
			<thead>
			<tr>
				<th>Entry</th>
				<th>Tags</th>
				<th style="width: 130px;">When</th>
				<th style="width: 130px;" class="right">&nbsp;</th>
			</tr>
			</thead>
			<tbody>
			@foreach ($entries as $entry)
				<tr class="tone-{{ \HungLv\Telescope\Support\Display::tone($entry) }}">
					<td>
						<a class="label {{ $entry->type === 'query' ? 'mono' : '' }}" href="{{ \HungLv\Telescope\Support\Display::link($entry) }}">{{ str_limit(\HungLv\Telescope\Support\Display::label($entry), 150) }}</a>
						<div class="muted mono">{{ \HungLv\Telescope\Support\Display::subtitle($entry) }}</div>
					</td>
					<td>
						@foreach ($entry->tags as $tag)
							<a class="tag {{ $tag }}" href="{{ \HungLv\Telescope\Support\Display::url(\HungLv\Telescope\Support\Display::segment($entry->type), ['tag' => $tag]) }}">{{ $tag }}</a>
						@endforeach
					</td>
					<td class="muted">{{ \HungLv\Telescope\Support\Display::ago($entry->createdAt) }}</td>
					<td class="right">{{ \HungLv\Telescope\Support\Display::meta($entry) }}</td>
				</tr>
			@endforeach
			</tbody>
		</table>

		@if ($next)
			<div class="toolbar"><a class="btn" href="{{ $next }}">Load older entries</a></div>
		@endif
	@endif
@stop

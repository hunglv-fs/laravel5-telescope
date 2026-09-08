<?php $active = 'insights'; ?>
@extends('telescope::layout')

@section('content')
	<h1>Insights</h1>
	<div class="muted">Where the time goes, and which queries repeat inside a single request, command or job.</div>

	<h2>N+1 suspects</h2>
	<div class="muted">The same query shape executed several times within one batch.</div>
	@if (count($duplicates) === 0)
		<div class="empty">No repeated query shapes recorded.</div>
	@else
		<table>
			<thead><tr><th style="width: 90px;">Runs</th><th>Query</th><th style="width: 150px;">Batch</th></tr></thead>
			<tbody>
			@foreach ($duplicates as $duplicate)
				<tr class="tone-warning">
					<td class="right">{{ $duplicate['occurrences'] }}&times;</td>
					<td class="mono label">
						@if ($duplicate['entry'])
							<a class="mono" href="{{ \HungLv\Telescope\Support\Display::link($duplicate['entry']) }}">{{ str_limit($duplicate['entry']->get('sql'), 150) }}</a>
							<?php $caller = $duplicate['entry']->get('caller'); ?>
							@if (is_array($caller))
								<div class="muted">{{ \HungLv\Telescope\Support\Display::relative($caller['file']) }}:{{ $caller['line'] }}</div>
							@endif
						@endif
					</td>
					<td><a href="{{ \HungLv\Telescope\Support\Display::url('batch/'.$duplicate['batch_id']) }}">{{ substr($duplicate['batch_id'], 0, 8) }}</a></td>
				</tr>
			@endforeach
			</tbody>
		</table>
	@endif

	<h2>Query hotspots</h2>
	<div class="muted">Recent queries grouped by shape, ordered by the total time they burned.</div>
	@if (count($hotspots) === 0)
		<div class="empty">No queries recorded.</div>
	@else
		<table>
			<thead><tr><th>Query</th><th style="width: 80px;" class="right">Calls</th><th style="width: 110px;" class="right">Total</th><th style="width: 110px;" class="right">Worst</th></tr></thead>
			<tbody>
			@foreach ($hotspots as $shape)
				<tr>
					<td class="mono label"><a class="mono" href="{{ \HungLv\Telescope\Support\Display::url('queries', ['family' => $shape['family_hash']]) }}">{{ str_limit($shape['sql'], 150) }}</a></td>
					<td class="right">{{ $shape['calls'] }}</td>
					<td class="right">{{ \HungLv\Telescope\Support\Display::duration($shape['total_time']) }}</td>
					<td class="right">{{ \HungLv\Telescope\Support\Display::duration($shape['max_time']) }}</td>
				</tr>
			@endforeach
			</tbody>
		</table>
	@endif

	@foreach (['Slowest requests' => $requests, 'Slowest jobs' => $jobs, 'Slowest commands' => $commands] as $heading => $slowest)
		@if (count($slowest) > 0)
			<h2>{{ $heading }}</h2>
			<table>
				<thead><tr><th>Entry</th><th style="width: 110px;" class="right">Queries</th><th style="width: 110px;" class="right">Duration</th></tr></thead>
				<tbody>
				@foreach ($slowest as $slow)
					<?php $slowStats = (array) $slow->get('stats', []); ?>
					<tr class="tone-{{ \HungLv\Telescope\Support\Display::tone($slow) }}">
						<td>
							<a class="label" href="{{ \HungLv\Telescope\Support\Display::link($slow) }}">{{ str_limit(\HungLv\Telescope\Support\Display::label($slow), 120) }}</a>
							<div class="muted">{{ \HungLv\Telescope\Support\Display::subtitle($slow) }}</div>
						</td>
						<td class="right">{{ isset($slowStats['queries']) ? $slowStats['queries'] : '-' }}</td>
						<td class="right">{{ \HungLv\Telescope\Support\Display::duration($slow->get('duration')) }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		@endif
	@endforeach
@stop

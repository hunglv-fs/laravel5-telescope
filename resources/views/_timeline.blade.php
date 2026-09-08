<div class="panel">
	<h3>Batch timeline &mdash; <a href="{{ \HungLv\Telescope\Support\Display::url('batch/'.$batchId) }}">{{ substr($batchId, 0, 8) }}</a></h3>
	<table>
		<tbody>
		@foreach ($batch as $item)
			<tr class="tone-{{ \HungLv\Telescope\Support\Display::tone($item) }}">
				<td style="width: 90px;"><span class="tag">{{ $item->type }}</span></td>
				<td>
					<a class="label {{ $item->type === 'query' ? 'mono' : '' }}" href="{{ \HungLv\Telescope\Support\Display::link($item) }}">{{ str_limit(\HungLv\Telescope\Support\Display::label($item), 120) }}</a>
					<div class="muted mono">{{ \HungLv\Telescope\Support\Display::subtitle($item) }}</div>
				</td>
				<td class="right">{{ \HungLv\Telescope\Support\Display::meta($item) }}</td>
			</tr>
		@endforeach
		</tbody>
	</table>
</div>

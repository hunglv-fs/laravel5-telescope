@extends('telescope::layout')

@section('content')
	<h1>Batch {{ substr($batchId, 0, 8) }}</h1>
	<div class="muted">{{ count($entries) }} entries recorded in this request, command or job.</div>

	<?php $batch = $entries; ?>
	@include('telescope::_timeline')
@stop

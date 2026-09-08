<?php $active = isset($active) ? $active : (isset($type) ? $type : null); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Telescope</title>
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; background: #0f1116; color: #d7dae0; font: 14px/1.5 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
		a { color: #7dd3fc; text-decoration: none; }
		a:hover { text-decoration: underline; }
		.wrap { display: flex; min-height: 100vh; }
		aside { width: 220px; flex: 0 0 220px; background: #161922; border-right: 1px solid #232735; padding: 18px 0; }
		.brand { font-size: 17px; font-weight: 600; color: #fff; padding: 0 18px 16px; }
		.brand span { font-size: 11px; color: #7c8496; font-weight: 400; }
		aside nav a { display: flex; justify-content: space-between; padding: 7px 18px; color: #aab1c0; border-left: 2px solid transparent; }
		aside nav a:hover { background: #1c2030; text-decoration: none; }
		aside nav a.active { background: #1c2030; color: #fff; border-left-color: #38bdf8; }
		aside nav a .count { color: #626b7e; font-size: 12px; }
		.sep { margin: 14px 18px; border-top: 1px solid #232735; }
		main { flex: 1; padding: 24px 28px; min-width: 0; }
		h1 { font-size: 20px; margin: 0 0 4px; color: #fff; font-weight: 600; }
		h2 { font-size: 15px; margin: 26px 0 10px; color: #fff; font-weight: 600; }
		.muted { color: #7c8496; font-size: 12px; }
		table { width: 100%; border-collapse: collapse; margin-top: 14px; }
		th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #626b7e; padding: 6px 10px; border-bottom: 1px solid #232735; font-weight: 600; }
		td { padding: 9px 10px; border-bottom: 1px solid #1b1f2b; vertical-align: top; }
		tr:hover td { background: #151925; }
		td.right { text-align: right; white-space: nowrap; color: #aab1c0; }
		.label { color: #e6e9ef; word-break: break-word; }
		.mono { font-family: "SFMono-Regular", Menlo, Consolas, monospace; font-size: 12.5px; }
		.tone-danger td.right, .tone-danger .label { color: #fca5a5; }
		.tone-warning td.right { color: #fcd34d; }
		.tag { display: inline-block; background: #232735; color: #9aa3b5; border-radius: 3px; padding: 1px 6px; font-size: 11px; margin-right: 4px; }
		.tag.slow { background: #422006; color: #fcd34d; }
		.tag.duplicate { background: #3b1616; color: #fca5a5; }
		.tag.failed, .tag.error { background: #3b1616; color: #fca5a5; }
		.panel { background: #161922; border: 1px solid #232735; border-radius: 6px; padding: 16px 18px; margin-bottom: 16px; }
		.panel h3 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #626b7e; font-weight: 600; }
		pre { background: #0b0d13; border: 1px solid #232735; border-radius: 4px; padding: 12px; overflow-x: auto; margin: 0 0 12px; color: #cbd5e1; font-family: "SFMono-Regular", Menlo, Consolas, monospace; font-size: 12.5px; }
		dl { display: grid; grid-template-columns: 180px 1fr; gap: 6px 14px; margin: 0; }
		dt { color: #7c8496; font-size: 12px; }
		dd { margin: 0; color: #e6e9ef; word-break: break-word; }
		.toolbar { display: flex; gap: 8px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
		input[type=text] { background: #0b0d13; border: 1px solid #2b3040; color: #d7dae0; border-radius: 4px; padding: 6px 10px; min-width: 260px; }
		button, .btn { background: #232735; border: 1px solid #2b3040; color: #d7dae0; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-size: 13px; }
		button:hover, .btn:hover { background: #2b3040; text-decoration: none; }
		.stats { display: flex; gap: 22px; flex-wrap: wrap; }
		.stat b { display: block; color: #fff; font-size: 18px; font-weight: 600; }
		.stat span { color: #7c8496; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
		.empty { color: #626b7e; padding: 40px 0; text-align: center; }
	</style>
</head>
<body>
<div class="wrap">
	<aside>
		<div class="brand">Telescope <span>for Laravel 5.0</span></div>
		<nav>
			<a href="{{ \HungLv\Telescope\Support\Display::url('insights') }}" class="{{ $active === 'insights' ? 'active' : '' }}">
				<span>Insights</span>
			</a>
			<div class="sep"></div>
			@foreach (\HungLv\Telescope\EntryType::all() as $navType)
				<a href="{{ \HungLv\Telescope\Support\Display::url(\HungLv\Telescope\Support\Display::segment($navType)) }}"
				   class="{{ $active === $navType ? 'active' : '' }}">
					<span>{{ \HungLv\Telescope\EntryType::label($navType) }}</span>
					<span class="count">{{ isset($counts[$navType]) ? $counts[$navType] : 0 }}</span>
				</a>
			@endforeach
		</nav>
		<div class="sep"></div>
		<form method="POST" action="{{ \HungLv\Telescope\Support\Display::url('clear') }}" style="padding: 0 18px;"
		      onsubmit="return confirm('Delete every Telescope entry?');">
			<input type="hidden" name="_token" value="{{ csrf_token() }}">
			<button type="submit">Clear entries</button>
		</form>
	</aside>
	<main>
		@yield('content')
	</main>
</div>
</body>
</html>

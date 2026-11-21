<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Your WatchWise week</title></head>
<body style="font-family:Arial,sans-serif;color:#241c3b;max-width:640px;margin:auto;padding:24px">
<h1>Your WatchWise week</h1>
<h2>This week's trending picks</h2>
<ul>
@forelse($picks as $pick)
<li><a href="{{ $pick['url'] }}">{{ $pick['title'] }}</a></li>
@empty
<li>No picks available this week. Explore the catalogue in WatchWise.</li>
@endforelse
</ul>
<h2>Friends' activity from the past seven days</h2>
@forelse($activity as $item)
<p><a href="{{ $item['url'] }}">{{ $item['name'] }} {{ $item['action'] }}</a>
@if($item['rating'] !== null)
 · {{ $item['rating'] }}/5 stars
@endif
</p>
@if($item['excerpt'])
<blockquote>{{ $item['excerpt'] }}</blockquote>
@endif
@empty
<p>No shared activity from the people you follow this week.</p>
@endforelse
<p><a href="{{ $homeUrl }}">Explore WatchWise</a></p>
<hr><p>You received this because you enabled Email digest. <a href="{{ $settingsUrl }}">Turn off Email digest in Settings → Integrations</a> to stop weekly emails.</p>
</body></html>

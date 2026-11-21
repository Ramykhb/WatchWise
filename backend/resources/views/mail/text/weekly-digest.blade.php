Your WatchWise week

THIS WEEK'S TRENDING PICKS
@forelse($picks as $pick)
{!! \App\Support\MailText::line($pick['title']) !!}: {!! $pick['url'] !!}
@empty
No picks available this week. Explore the catalogue in WatchWise.
@endforelse

FRIENDS' ACTIVITY FROM THE PAST SEVEN DAYS
@forelse($activity as $item)
{!! \App\Support\MailText::line($item['name'].' '.$item['action']) !!}
@if($item['rating'] !== null)
 · {{ $item['rating'] }}/5 stars
@endif

@if($item['excerpt'])
{!! \App\Support\MailText::line($item['excerpt']) !!}
@endif
{!! $item['url'] !!}

@empty
No shared activity from the people you follow this week.
@endforelse

Explore WatchWise: {{ $homeUrl }}

You received this because you enabled Email digest.
Turn off Email digest in Settings → Integrations to stop weekly emails: {{ $settingsUrl }}

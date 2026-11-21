WATCHWISE MOVIE NIGHT

{!! \App\Support\MailText::line($title) !!}

{{ $cancelled ? 'Calendar event cancelled. The night was cancelled or voting was reopened.' : 'Your movie night is confirmed. Add or update it using the attached calendar invitation.' }}

Movie: {!! \App\Support\MailText::line($movieTitle) !!}
When: {!! $when !!}
Location: {!! \App\Support\MailText::line($location) !!}
@if($meetingLink)
Online: {!! \App\Support\MailText::line($meetingLink) !!}
@endif
@if($note)
Note: {!! \App\Support\MailText::line($note) !!}
@endif

View current details:
{!! $url !!}

Calendar invitations display times in your calendar's own time zone.

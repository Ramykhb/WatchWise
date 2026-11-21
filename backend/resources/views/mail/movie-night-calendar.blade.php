@include('mail.layout', [
    'preview' => $cancelled ? 'This movie night was cancelled or voting reopened.' : 'Your movie night is confirmed. A calendar invitation is attached.',
    'eyebrow' => 'MOVIE NIGHT',
    'heading' => $cancelled ? 'Calendar event cancelled' : $title,
    'intro' => 'Movie: '.$movieTitle.'. When: '.$when.'. Location: '.$location.'.',
    'instruction' => $cancelled ? 'The attached calendar cancellation updates the previous invitation. Open the movie night for its current status.' : 'Open the attached calendar invitation to add or update this event. Calendar times are stored in UTC and shown in your own time zone.',
    'action' => 'View movie night',
    'note' => implode(' ', array_filter([$meetingLink, $note])),
    'signoff' => 'See you at the movies,',
])

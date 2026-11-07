@include('mail.layout', [
    'preview' => 'One quick confirmation. A whole world of great watches.',
    'eyebrow' => 'YOUR WATCHWISE DEBUT',
    'heading' => 'Great stories start here.',
    'intro' => 'Hi '.$user->name.', welcome to WatchWise! Your next favorite film, your kind of people, and a better movie night are waiting.',
    'instruction' => 'First, let’s make sure this email belongs to you.',
    'action' => 'Verify my email',
    'note' => 'If you didn’t create a WatchWise account, you can safely ignore this email.',
    'signoff' => 'See you at movie night,',
])

@include('mail.layout', [
    'preview' => 'One click and this becomes your WatchWise sign-in email.',
    'eyebrow' => 'NEW EMAIL, SAME YOU',
    'heading' => 'Is this your new address?',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', you asked to sign in to WatchWise with this email from now on.',
    'instruction' => 'Confirm it’s yours and we’ll make the switch. Until then, your current email keeps working.',
    'action' => 'Confirm my new email',
    'note' => 'If you didn’t ask for this, ignore this email: nothing changes unless the link is opened.',
    'signoff' => 'See you at movie night,',
])

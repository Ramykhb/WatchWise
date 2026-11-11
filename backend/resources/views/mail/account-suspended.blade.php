@include('mail.layout', [
    'preview' => 'Your WatchWise account has been suspended.',
    'eyebrow' => 'ACCOUNT STATUS',
    'heading' => 'Your account was suspended.',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', your WatchWise account has been suspended by the WatchWise team.',
    'instruction' => 'While it is suspended you can’t sign in. If the suspension is lifted, we’ll let you know by email.',
    'note' => 'This is an automated message about the status of your account.',
    'signoff' => 'Thank you for understanding,',
])

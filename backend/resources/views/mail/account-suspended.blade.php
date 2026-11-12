@include('mail.layout', [
    'preview' => 'Your WatchWise account has been suspended.',
    'eyebrow' => 'ACCOUNT STATUS',
    'heading' => 'Your account was suspended.',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', your WatchWise account has been suspended by the WatchWise team.',
    'instruction' => $instruction,
    'note' => 'This is an automated message about the status of your account.',
    'signoff' => 'Thank you for understanding,',
])

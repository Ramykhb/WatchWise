@include('mail.layout', [
    'preview' => 'Your WatchWise password was just reset.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'Your password was changed.',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', the password of your WatchWise account was just reset with the link we emailed you, and every device was signed out.',
    'instruction' => 'If you did this, there’s nothing more to do. Wasn’t you? Reset your password again right away: that signs out every device once more.',
    'action' => 'Reset my password',
    'note' => 'If you can’t get into your account, contact WatchWise support so we can secure it.',
    'signoff' => 'Keeping your seat safe,',
])

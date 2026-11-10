@include('mail.layout', [
    'preview' => 'A password was just added to your WatchWise account.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'A password was added.',
    'intro' => 'Hi '.$name.', a password was just added to your WatchWise account. You can now sign in with your email and that password as well as with your social account.',
    'instruction' => 'If you did this, there’s nothing more to do. If it wasn’t you, reset your password right away: that signs out every other device.',
    'action' => 'Reset my password',
    'note' => 'If you can’t get into your account, contact WatchWise support so we can secure it.',
    'signoff' => 'Keeping your seat safe,',
])

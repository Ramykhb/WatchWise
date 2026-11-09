@include('mail.layout', [
    'preview' => 'Your WatchWise sign-in email is now '.$maskedNewEmail.'.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'Your email was changed.',
    'intro' => 'Hi '.$name.', the email on your WatchWise account is now '.$maskedNewEmail.'. We won’t send account emails to this address anymore, and every device was signed out.',
    'instruction' => 'If you made this change, you’re all set: sign in with your new email.',
    'action' => 'Sign in to WatchWise',
    'note' => 'If you didn’t make this change, contact WatchWise support right away so we can secure your account.',
    'signoff' => 'Keeping your seat safe,',
])

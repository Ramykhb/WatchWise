@include('mail.layout', [
    'preview' => 'Your WatchWise account is active again.',
    'eyebrow' => 'ACCOUNT STATUS',
    'heading' => 'Your account is active again.',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', the WatchWise team has lifted the suspension of your account.',
    'instruction' => 'You can sign in again whenever you like.',
    'action' => 'Sign in',
    'note' => 'This is an automated message about the status of your account.',
    'signoff' => 'Welcome back,',
])

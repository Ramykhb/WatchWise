@include('mail.layout', [
    'preview' => 'A fresh password. Then back to the good stuff.',
    'eyebrow' => 'A LITTLE PLOT TWIST',
    'heading' => 'Let’s get you back in.',
    'intro' => 'Hi '.\App\Support\MailText::line($name).'. Forgot your password? It happens. We received a request to reset the password for your WatchWise account.',
    'instruction' => 'Choose a new password and get back to your next great watch.',
    'action' => 'Reset my password',
    'note' => 'If you didn’t request this, no action is needed. Your password stays the same. This link can only be used once.',
    'signoff' => 'Your next watch is waiting,',
])

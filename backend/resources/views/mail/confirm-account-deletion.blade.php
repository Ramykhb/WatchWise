@include('mail.layout', [
    'preview' => 'Confirm that you want to delete your WatchWise account.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'Deleting your account?',
    'intro' => 'Hi '.\App\Support\MailText::line($name).', someone signed in to your WatchWise account asked to delete it. Nothing is deleted until you confirm here.',
    'instruction' => 'If this was you, confirm below. Your profile, reviews, ratings, lists and history are removed for good, and this can’t be undone.',
    'action' => 'Yes, delete my account',
    'secondaryPrompt' => 'Wasn’t you?',
    'secondaryAction' => 'Keep my account',
    'secondaryUrl' => $cancelUrl,
    'note' => 'If you didn’t ask for this, don’t open the confirmation link: keep your account with the link above, then reset your password with “Forgot password” on the sign-in page. Nothing is deleted unless the confirmation link is opened.',
    'signoff' => 'Keeping your seat safe,',
])

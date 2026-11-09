@include('mail.layout', [
    'preview' => 'Someone asked to move your WatchWise account to '.$maskedNewEmail.'.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'Changing your email?',
    'intro' => 'Hi '.$name.', someone asked to change the email on your WatchWise account to '.$maskedNewEmail.'. Nothing changes until that address confirms it.',
    'instruction' => 'If this was you, there’s nothing to do here. If it wasn’t, cancel the change in your profile and change your password.',
    'action' => 'Review my account',
    'note' => 'Your sign-in email stays the same unless the link sent to the new address is opened.',
    'signoff' => 'Keeping your seat safe,',
])

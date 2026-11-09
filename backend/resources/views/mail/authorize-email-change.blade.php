@include('mail.layout', [
    'preview' => 'Approve moving your WatchWise account to '.$maskedNewEmail.'.',
    'eyebrow' => 'ACCOUNT SECURITY',
    'heading' => 'Changing your email?',
    'intro' => 'Hi '.$name.', someone signed in to your WatchWise account asked to change its email to '.$maskedNewEmail.'. We won’t contact that address until you approve it here.',
    'instruction' => 'If this was you, confirm below and we’ll send the last step to your new address. Until the change is done, this email keeps working.',
    'action' => 'Yes, change my email',
    'secondaryPrompt' => 'Wasn’t you?',
    'secondaryAction' => 'Cancel this change',
    'secondaryUrl' => $cancelUrl,
    'note' => 'If you didn’t ask for this, don’t open the approval link: cancel the change, then set a password in your profile. Nothing changes unless the approval link is opened.',
    'signoff' => 'Keeping your seat safe,',
])

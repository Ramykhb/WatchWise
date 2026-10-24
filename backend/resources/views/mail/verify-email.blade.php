<x-mail::message>
# Verify your email

Hi {{ $user->name }}, welcome to WatchWise! Please confirm your email address.

<x-mail::button :url="$url">
Verify email
</x-mail::button>

This link expires in {{ $expiresInMinutes }} minutes. If you did not create an account, ignore this email.
</x-mail::message>

<x-mail::message>
# Reset your password

Hi {{ $name }}, we received a request to reset your WatchWise password.

<x-mail::button :url="$url">
Reset password
</x-mail::button>

This link expires in {{ $expiresInMinutes }} minutes and can be used once. If you did not request this, ignore this email.
</x-mail::message>

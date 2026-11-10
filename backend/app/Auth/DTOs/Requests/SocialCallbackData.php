<?php

namespace App\Auth\DTOs\Requests;

/** What the provider's callback brought back, plus the client details the new session is recorded with. */
final readonly class SocialCallbackData
{
    public function __construct(
        public string $provider,
        /** The echoed `state` equals the `oauth_state` cookie and was issued for this provider. */
        public bool $stateMatches,
        /** The authorization code (read by Discord's client; Socialite reads it from the request itself). */
        public ?string $code,
        /** The OAuth `error` parameter, e.g. `access_denied` when the user cancelled the consent screen. */
        public ?string $error,
        public ?string $userAgent,
        public ?string $ip,
    ) {}
}

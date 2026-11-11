<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The provider has no real OAuth client id, secret or redirect URI in the backend environment. Both social routes
 * are opened in the browser, so this becomes a `provider_unavailable` redirect rather than a JSON error.
 */
class SocialLoginNotConfigured extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public static function forProvider(string $provider): self
    {
        return new self(ucfirst($provider).' sign-in is not configured. Add valid OAuth client credentials and redirect URI to the backend environment.');
    }

    public function reason(): string
    {
        return 'provider_unavailable';
    }
}

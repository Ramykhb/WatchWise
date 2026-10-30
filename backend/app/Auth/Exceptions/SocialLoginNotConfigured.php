<?php

namespace App\Auth\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The provider has no real OAuth client credentials in the backend environment. Rendered as a 503. */
class SocialLoginNotConfigured extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self(ucfirst($provider).' sign-in is not configured. Add valid OAuth client credentials to the backend environment.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}

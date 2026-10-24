<?php

namespace App\Auth\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google gives accounts without a photo a generated letter image whose URL looks like a real
 * photo's. Only the People API says which it is (`photos[].default`), so ask it with the user's
 * access token. Needs the People API enabled in the Google Cloud project.
 */
class GooglePhoto
{
    /** true = generated placeholder, false = a real photo, null = could not tell (API off or down). */
    public function isPlaceholder(?string $accessToken): ?bool
    {
        if (! $accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(5)
                ->get('https://people.googleapis.com/v1/people/me', ['personFields' => 'photos']);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $primary = collect($response->json('photos', []))->firstWhere('metadata.primary', true)
            ?? $response->json('photos.0');

        return $primary === null ? true : (bool) ($primary['default'] ?? false);
    }
}

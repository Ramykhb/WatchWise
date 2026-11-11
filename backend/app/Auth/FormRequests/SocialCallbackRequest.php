<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\SocialCallbackData;
use App\Auth\Support\OAuthState;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The provider's redirect back to GET /api/auth/social/{provider}/callback. It has no validation rules: the callback
 * is a browser navigation that must always end in a redirect to the frontend, so every value is read leniently
 * (non-strings become null) and `SocialAuthServiceInterface::loginWithProvider()` decides what is acceptable.
 * Anyone may call it; the state check is the guard.
 */
class SocialCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): SocialCallbackData
    {
        $provider = (string) $this->route('provider');

        return new SocialCallbackData(
            provider: $provider,
            stateMatches: OAuthState::matches($this->cookie(OAuthState::cookieName()), $this->query('state'), $provider),
            code: $this->stringQuery('code'),
            error: $this->stringQuery('error'),
            userAgent: $this->userAgent(),
            ip: $this->ip(),
        );
    }

    private function stringQuery(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

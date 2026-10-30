<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\SocialLoginNotConfigured;
use App\Auth\Exceptions\SocialProviderFailed;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use Illuminate\Http\Request;

interface SocialAuthServiceInterface
{
    /**
     * Where to send the browser to sign in with the provider. `$state` comes back on the callback.
     *
     * @throws SocialLoginNotConfigured when the provider has no real OAuth credentials
     */
    public function redirectUrl(string $provider, string $state): string;

    /**
     * Exchanges the callback for the provider's user. `$code` is the authorization code Discord sends
     * back; the Socialite providers read it from the current request themselves.
     *
     * @throws SocialProviderFailed when Discord answers with something unusable (a failed Socialite or HTTP call throws its own error)
     */
    public function providerUser(string $provider, ?string $code): SocialLoginData;

    /**
     * @throws EmailRequired when the provider gave no email
     * @throws UnverifiedProviderEmail when the provider has not verified the email
     * @throws AccountDisabled when the user is inactive
     */
    public function loginWithProvider(SocialLoginData $data, Request $request): AuthResponse;
}

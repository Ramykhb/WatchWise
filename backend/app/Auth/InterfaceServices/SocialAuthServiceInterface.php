<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\SocialCallbackData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\InvalidOAuthState;
use App\Auth\Exceptions\RegistrationClosed;
use App\Auth\Exceptions\SocialAccountNotSaved;
use App\Auth\Exceptions\SocialLoginCancelled;
use App\Auth\Exceptions\SocialLoginNotConfigured;
use App\Auth\Exceptions\SocialProviderFailed;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Models\User;

interface SocialAuthServiceInterface
{
    /**
     * Where to send the browser to sign in with the provider. `$state` comes back on the callback.
     *
     * @throws SocialLoginNotConfigured when the provider has no real OAuth client id, secret or redirect URI
     */
    public function redirectUrl(string $provider, string $state): string;

    /**
     * Completes the provider's callback: checks the state, exchanges the code for the provider's user, then finds,
     * links or creates the account and issues a session. A rejected sign-in changes nothing.
     *
     * Every expected outcome other than success is a `SocialLoginFailure`; anything else (including
     * `SocialProviderFailed`, `SocialAccountNotSaved` and a failed Socialite or HTTP call) is a real failure.
     *
     * @throws InvalidOAuthState when the state is missing, wrong or issued for another provider
     * @throws SocialLoginNotConfigured when the provider has no real OAuth client id, secret or redirect URI
     * @throws SocialLoginCancelled when the user declined the consent screen
     * @throws SocialProviderFailed when the provider answers with something unusable (no code, token or user id)
     * @throws EmailRequired when the provider identity is not linked yet and the provider gave no email
     * @throws UnverifiedProviderEmail when the provider has not verified the email
     * @throws AccountDisabled when the account is inactive
     * @throws RegistrationClosed when the sign-in would create a new account and an admin closed registration
     * @throws SocialAccountNotSaved when writing the account fails (a database error, or still colliding with concurrent sign-ins)
     */
    public function loginWithProvider(SocialCallbackData $callback): AuthResponse;

    /**
     * The names of the providers linked to the account (`github`, `google`...), alphabetically. Never the
     * provider's ids for the account.
     *
     * @return list<string>
     */
    public function linkedProviders(User $user): array;
}

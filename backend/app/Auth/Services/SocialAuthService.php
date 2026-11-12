<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\SocialCallbackData;
use App\Auth\DTOs\Requests\SocialLoginData;
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
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\DiscordOAuthClientInterface;
use App\Auth\InterfaceServices\GooglePhotoInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\Support\EmailAddress;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthService implements SocialAuthServiceInterface
{
    /** Tries at finding, linking or creating the account when concurrent callbacks collide on a unique index. */
    private const RESOLVE_ATTEMPTS = 3;

    /**
     * Whether a provider's email can be trusted as verified. `null` means the provider reports it per user
     * (`SocialLoginData::$providerReportsEmailVerified`). GitHub is trusted only because Socialite's GitHub driver
     * keeps its default `user:email` scope and then takes the email from /user/emails, returning the PRIMARY address
     * only when GitHub marks it verified (otherwise none, which is `email_required`); requesting other scopes
     * without `user:email` would break that. Any provider not listed is never trusted.
     *
     * @var array<string, bool|null>
     */
    private const EMAIL_VERIFICATION = [
        'google' => null,
        'discord' => null,
        'github' => true,
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly TokenFactory $tokens,
        private readonly DiscordOAuthClientInterface $discord,
        private readonly GooglePhotoInterface $googlePhoto,
        private readonly EmailChangeServiceInterface $emailChanges,
        private readonly SettingServiceInterface $settings,
    ) {}

    public function redirectUrl(string $provider, string $state): string
    {
        $this->assertConfigured($provider);

        if ($provider === 'discord') {
            return $this->discord->authorizeUrl($state);
        }

        return Socialite::driver($provider)->stateless()->with(['state' => $state])->redirect()->getTargetUrl();
    }

    public function linkedProviders(User $user): array
    {
        return $this->socialAccounts->providerNamesOf($user->id);
    }

    public function loginWithProvider(SocialCallbackData $callback): AuthResponse
    {
        if (! $callback->stateMatches) {
            throw new InvalidOAuthState;
        }

        $this->assertConfigured($callback->provider);

        if ($callback->error === 'access_denied') {
            throw new SocialLoginCancelled;
        }

        if ($callback->error !== null) {
            // The value comes from the query string and ends up in the log, so only a plain OAuth error code is kept.
            $error = preg_match('/^[a-z_]{1,64}$/', $callback->error) === 1 ? $callback->error : 'unknown';

            throw new SocialProviderFailed(ucfirst($callback->provider)." returned the OAuth error \"{$error}\".");
        }

        $user = $this->resolveUser($this->providerUser($callback->provider, $callback->code));

        return AuthResponse::fromTokenPair($this->tokens->issue($user, $this->clientRequest($callback)));
    }

    /**
     * Logs a warning naming the provider and the first unusable key (never its value), since the exception
     * itself is not reported.
     *
     * @throws SocialLoginNotConfigured when the client id, secret or redirect URI is missing or still the `CHANGE_ME` placeholder
     */
    private function assertConfigured(string $provider): void
    {
        $credentials = config("services.{$provider}");

        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            $value = is_array($credentials) ? ($credentials[$key] ?? null) : null;

            if (! is_string($value) || trim($value) === '' || str_contains(strtoupper($value), 'CHANGE_ME')) {
                Log::warning('Social sign-in is not configured.', ['provider' => $provider, 'missing' => "services.{$provider}.{$key}"]);

                throw SocialLoginNotConfigured::forProvider($provider);
            }
        }
    }

    /**
     * The provider's user. Google's letter image for accounts without a photo counts as no photo (initials are shown).
     *
     * @throws SocialProviderFailed when the provider gives no user id
     */
    private function providerUser(string $provider, ?string $code): SocialLoginData
    {
        $data = $provider === 'discord'
            ? $this->discord->user($code)
            : SocialLoginData::fromSocialite($provider, Socialite::driver($provider)->stateless()->user());

        if (trim($data->providerId) === '') {
            throw new SocialProviderFailed(ucfirst($provider).' did not return a user ID.');
        }

        if ($provider === 'google' && $this->googlePhoto->isPlaceholder($data->accessToken) === true) {
            return $data->withoutPlaceholderAvatar();
        }

        return $data;
    }

    /**
     * Two callbacks for the same new person (a double click, two tabs) can both miss the lookups and then collide on
     * a unique index (users.email, users.username, social_accounts provider + id). The loser's transaction is rolled
     * back, and another pass finds the rows the winner committed and signs in to that account.
     *
     * A database failure is rethrown as `SocialAccountNotSaved`, whose message holds no SQL or personal data.
     *
     * @throws SocialAccountNotSaved
     */
    private function resolveUser(SocialLoginData $data): User
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->findLinkOrCreateUser($data);
            } catch (UniqueConstraintViolationException) {
                if ($attempt >= self::RESOLVE_ATTEMPTS) {
                    throw SocialAccountNotSaved::afterConflicts(self::RESOLVE_ATTEMPTS);
                }
            } catch (QueryException $e) {
                throw SocialAccountNotSaved::fromQuery($e);
            }
        }
    }

    /**
     * Every rule is checked before the first write, so a refused sign-in leaves the account untouched.
     *
     * Creating a new account is the only step registration being closed refuses: a linked identity, and an existing
     * account matched by email, still sign in.
     *
     * @throws EmailRequired|UnverifiedProviderEmail|AccountDisabled|RegistrationClosed
     */
    private function findLinkOrCreateUser(SocialLoginData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $account = $this->socialAccounts->findByProvider($data->provider, $data->providerId);

            if ($account) {
                $user = $account->user;

                if (! $user->is_active) {
                    throw new AccountDisabled;
                }

                $this->dropPlaceholderAvatar($user, $data);

                return $user;
            }

            // Only an identity that is not linked yet needs the email: it is how accounts are matched and created.
            if (trim((string) $data->email) === '') {
                throw new EmailRequired;
            }

            // Never link to or create an account from an email the provider has not verified.
            if (! $this->emailIsVerified($data)) {
                throw new UnverifiedProviderEmail;
            }

            // Providers may report "Ali@Example.com"; accounts are found and created by the lowercase address.
            $email = EmailAddress::normalize((string) $data->email);
            $user = $this->users->findByEmail($email);

            if ($user) {
                if (! $user->is_active) {
                    throw new AccountDisabled;
                }

                if (! $user->email_verified_at) {
                    // The local account was never proven to belong to this email, so whoever registered it may
                    // not be the owner: drop their password, sessions and any email change they started (its
                    // "resend" would otherwise mail a fresh confirmation link to their address).
                    $this->users->markEmailVerified($user);
                    $this->users->clearPassword($user);
                    $this->refreshTokens->revokeAllForUser($user);
                    $this->emailChanges->discardPendingChange($user);
                }
            } else {
                if (! $this->settings->registrationOpen()) {
                    throw new RegistrationClosed;
                }

                $user = $this->users->create([
                    'name' => $data->name ?: ($data->nickname ?: Str::before($email, '@')),
                    'email' => $email,
                    'password' => null,
                    'avatar' => $data->avatar,
                ]);
                $this->users->markEmailVerified($user);
            }

            $this->socialAccounts->create([
                'user_id' => $user->id,
                'provider' => $data->provider,
                'provider_id' => $data->providerId,
            ]);

            return $user;
        });
    }

    /**
     * Clears a placeholder saved by an earlier sign-in. Only a photo that came from Google is
     * touched; one the user uploaded in Settings stays.
     */
    private function dropPlaceholderAvatar(User $user, SocialLoginData $data): void
    {
        if ($data->avatarIsPlaceholder && is_string($user->avatar) && str_contains($user->avatar, 'googleusercontent.com')) {
            $this->users->clearAvatar($user);
        }
    }

    /** Fails closed: see `EMAIL_VERIFICATION`. */
    private function emailIsVerified(SocialLoginData $data): bool
    {
        if (! array_key_exists($data->provider, self::EMAIL_VERIFICATION)) {
            return false;
        }

        return self::EMAIL_VERIFICATION[$data->provider] ?? $data->providerReportsEmailVerified;
    }

    /**
     * `TokenFactory::issue()` records the session's user agent and IP from a request, so rebuild one from the
     * callback's client details rather than passing the HTTP request below the controller.
     */
    private function clientRequest(SocialCallbackData $callback): Request
    {
        return Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => (string) $callback->ip,
            'HTTP_USER_AGENT' => (string) $callback->userAgent,
        ]);
    }
}

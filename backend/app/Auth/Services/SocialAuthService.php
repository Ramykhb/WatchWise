<?php

namespace App\Auth\Services;

use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Support\TokenPair;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;

class SocialAuthService implements SocialAuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly TokenFactory $tokens,
    ) {}

    public function loginWithProvider(string $provider, SocialUser $socialUser, Request $request): TokenPair
    {
        if (! $socialUser->getEmail()) {
            throw new EmailRequired;
        }

        $user = $this->resolveUser($provider, $socialUser);

        if (! $user->is_active) {
            throw new AccountDisabled;
        }

        return $this->tokens->issue($user, $request);
    }

    private function resolveUser(string $provider, SocialUser $socialUser): User
    {
        return DB::transaction(function () use ($provider, $socialUser): User {
            $providerId = (string) $socialUser->getId();
            $account = $this->socialAccounts->findByProvider($provider, $providerId);

            if ($account) {
                $this->socialAccounts->updateAccessToken($account, $socialUser->token);

                return $account->user;
            }

            $email = $socialUser->getEmail();

            // Never link to or create an account from an email the provider has not verified.
            if (! $this->emailIsVerified($provider, $socialUser)) {
                throw new UnverifiedProviderEmail;
            }

            $user = $this->users->findByEmail($email);

            if ($user) {
                if (! $user->email_verified_at) {
                    // The local account was never proven to belong to this email, so whoever
                    // registered it may not be the owner: drop their password and sessions.
                    $this->users->markEmailVerified($user);
                    $this->users->updatePassword($user, Str::random(40));
                    $this->refreshTokens->revokeAllForUser($user->id);
                }
            } else {
                $user = $this->users->create([
                    'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: Str::before($email, '@')),
                    'email' => $email,
                    'password' => Str::random(40),
                    'avatar' => $socialUser->getAvatar(),
                ]);
                $this->users->markEmailVerified($user);
            }

            $this->socialAccounts->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerId,
                'access_token' => $socialUser->token,
            ]);

            return $user;
        });
    }

    private function emailIsVerified(string $provider, SocialUser $socialUser): bool
    {
        if ($provider === 'google') {
            return (bool) ($socialUser->user['email_verified'] ?? false);
        }

        return true;
    }
}

<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAuthService implements SocialAuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly TokenFactory $tokens,
    ) {}

    public function loginWithProvider(SocialLoginData $data, Request $request): AuthResponse
    {
        if (! $data->email) {
            throw new EmailRequired;
        }

        $user = $this->resolveUser($data);

        if (! $user->is_active) {
            throw new AccountDisabled;
        }

        return AuthResponse::fromTokenPair($this->tokens->issue($user, $request));
    }

    private function resolveUser(SocialLoginData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $account = $this->socialAccounts->findByProvider($data->provider, $data->providerId);

            if ($account) {
                $this->socialAccounts->updateAccessToken($account, $data->accessToken);

                return $account->user;
            }

            $email = (string) $data->email;

            // Never link to or create an account from an email the provider has not verified.
            if (! $this->emailIsVerified($data)) {
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
                    'name' => $data->name ?: ($data->nickname ?: Str::before($email, '@')),
                    'email' => $email,
                    'password' => Str::random(40),
                    'avatar' => $data->avatar,
                ]);
                $this->users->markEmailVerified($user);
            }

            $this->socialAccounts->create([
                'user_id' => $user->id,
                'provider' => $data->provider,
                'provider_id' => $data->providerId,
                'access_token' => $data->accessToken,
            ]);

            return $user;
        });
    }

    private function emailIsVerified(SocialLoginData $data): bool
    {
        if ($data->provider === 'google') {
            return $data->providerReportsEmailVerified;
        }

        return true;
    }
}

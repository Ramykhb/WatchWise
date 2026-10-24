<?php

namespace App\Auth\DTOs\Requests;

use Laravel\Socialite\Contracts\User as SocialiteUser;

/** What the auth module needs from an OAuth provider, decoupled from Socialite. */
final readonly class SocialLoginData
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public ?string $email,
        public ?string $name,
        public ?string $nickname,
        public ?string $avatar,
        public ?string $accessToken,
        public bool $providerReportsEmailVerified,
    ) {}

    public static function fromSocialite(string $provider, SocialiteUser $socialUser): self
    {
        return new self(
            provider: $provider,
            providerId: (string) $socialUser->getId(),
            email: $socialUser->getEmail() ?: null,
            name: $socialUser->getName() ?: null,
            nickname: $socialUser->getNickname() ?: null,
            avatar: $socialUser->getAvatar() ?: null,
            accessToken: $socialUser->token ?? null,
            providerReportsEmailVerified: (bool) ($socialUser->user['email_verified'] ?? false),
        );
    }
}

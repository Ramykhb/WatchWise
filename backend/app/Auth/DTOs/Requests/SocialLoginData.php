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
        /** The provider confirmed the account has no photo of its own (Google's letter image). */
        public bool $avatarIsPlaceholder = false,
    ) {}

    /** The same data with no photo, for providers that send a generated placeholder. */
    public function withoutPlaceholderAvatar(): self
    {
        return new self(
            provider: $this->provider,
            providerId: $this->providerId,
            email: $this->email,
            name: $this->name,
            nickname: $this->nickname,
            avatar: null,
            accessToken: $this->accessToken,
            providerReportsEmailVerified: $this->providerReportsEmailVerified,
            avatarIsPlaceholder: true,
        );
    }

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

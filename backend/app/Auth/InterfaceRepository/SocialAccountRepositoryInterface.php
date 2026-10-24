<?php

namespace App\Auth\InterfaceRepository;

use App\Models\SocialAccount;

interface SocialAccountRepositoryInterface
{
    public function findByProvider(string $provider, string $providerId): ?SocialAccount;

    /**
     * @param  array{user_id: int, provider: string, provider_id: string, access_token?: string|null}  $attributes
     */
    public function create(array $attributes): SocialAccount;

    public function updateAccessToken(SocialAccount $account, ?string $accessToken): void;
}

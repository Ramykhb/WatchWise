<?php

namespace App\Auth\InterfaceRepository;

use App\Models\SocialAccount;

interface SocialAccountRepositoryInterface
{
    public function findByProvider(string $provider, string $providerId): ?SocialAccount;

    /**
     * The provider's access token is never stored: nothing reads it after sign-in.
     *
     * @param  array{user_id: int, provider: string, provider_id: string}  $attributes
     */
    public function create(array $attributes): SocialAccount;
}

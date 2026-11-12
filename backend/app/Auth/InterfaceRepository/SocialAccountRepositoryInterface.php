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

    /**
     * The names of the providers the user signed in with, alphabetically; never their ids.
     *
     * @return list<string>
     */
    public function providerNamesOf(int $userId): array;
}

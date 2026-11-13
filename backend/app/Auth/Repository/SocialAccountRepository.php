<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Models\SocialAccount;

class SocialAccountRepository implements SocialAccountRepositoryInterface
{
    public function findByProvider(string $provider, string $providerId): ?SocialAccount
    {
        return SocialAccount::where('provider', $provider)->where('provider_id', $providerId)->first();
    }

    public function create(array $attributes): SocialAccount
    {
        return SocialAccount::create($attributes);
    }

    public function providerNamesOf(int $userId): array
    {
        return SocialAccount::query()->where('user_id', $userId)->orderBy('provider')->pluck('provider')->all();
    }
}

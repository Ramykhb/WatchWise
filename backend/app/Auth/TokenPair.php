<?php

namespace App\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

final class TokenPair
{
    public function __construct(
        public readonly User $user,
        public readonly string $accessToken,
        public readonly CarbonInterface $accessExpiresAt,
        public readonly string $refreshToken,
        public readonly CarbonInterface $refreshExpiresAt,
    ) {}
}

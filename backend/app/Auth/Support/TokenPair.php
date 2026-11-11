<?php

namespace App\Auth\Support;

use App\Models\User;
use Carbon\CarbonInterface;

final class TokenPair
{
    /**
     * @param  bool  $remember  the login's "Remember me" choice: false makes both cookies session cookies
     */
    public function __construct(
        public readonly User $user,
        public readonly string $accessToken,
        public readonly CarbonInterface $accessExpiresAt,
        public readonly string $refreshToken,
        public readonly CarbonInterface $refreshExpiresAt,
        public readonly bool $remember = true,
    ) {}
}

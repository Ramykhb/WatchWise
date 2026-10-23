<?php

namespace App\Auth\InterfaceServices;

use App\Auth\Exceptions\InvalidResetToken;

interface PasswordResetServiceInterface
{
    /** Always silent: all work for an existing account is deferred so the response never reveals it. */
    public function sendResetLink(string $email): void;

    /** @throws InvalidResetToken */
    public function reset(string $email, string $token, string $password): void;
}

<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\ForgotPasswordData;
use App\Auth\DTOs\Requests\ResetPasswordData;
use App\Auth\Exceptions\InvalidResetToken;

interface PasswordResetServiceInterface
{
    /** Always silent: all work for an existing account is deferred so the response never reveals it. */
    public function sendResetLink(ForgotPasswordData $data): void;

    /** @throws InvalidResetToken */
    public function reset(ResetPasswordData $data): void;
}

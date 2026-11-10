<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** PUT /api/me/password/set is only for accounts without a password; the rest change it instead. Rendered as a 409. */
class PasswordAlreadySet extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Your account already has a password. Change it instead.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'password_already_set'], 409);
    }
}

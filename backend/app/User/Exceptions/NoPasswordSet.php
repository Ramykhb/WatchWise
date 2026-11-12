<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** PUT /api/me/password changes an existing password; an account without one sets it first. Rendered as a 409. */
class NoPasswordSet extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Your account doesn't have a password yet. Create one first.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'no_password_set'], 409);
    }
}

<?php

namespace App\Auth\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The email and password do not match an account. Rendered as a 401. */
class InvalidCredentials extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }
}

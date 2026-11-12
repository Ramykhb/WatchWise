<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A registration lost the race for its email to a sign-up that committed first (both passed validation). Rendered
 * exactly like the `UniqueEmail` validation failure: 422 with the error on `email`.
 */
class EmailAlreadyRegistered extends RuntimeException implements ShouldntReport
{
    public function render(): JsonResponse
    {
        $message = trans('validation.unique', ['attribute' => 'email']);

        return response()->json(['message' => $message, 'errors' => ['email' => [$message]]], 422);
    }
}

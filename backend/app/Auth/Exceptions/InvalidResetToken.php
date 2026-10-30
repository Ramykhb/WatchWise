<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The password reset token is wrong, used or expired. Rendered as a 422. */
class InvalidResetToken extends RuntimeException implements ShouldntReport
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Invalid or expired reset token.'], 422);
    }
}

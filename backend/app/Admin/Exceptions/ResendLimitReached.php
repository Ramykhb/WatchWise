<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The verification email of this member was already resent three times this minute. Rendered as a 429 with `Retry-After`. */
class ResendLimitReached extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('This member was sent too many verification emails. Try again in a moment.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 429, ['Retry-After' => $this->retryAfterSeconds]);
    }
}

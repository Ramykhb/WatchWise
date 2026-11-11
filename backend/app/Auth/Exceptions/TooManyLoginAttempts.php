<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Too many failed sign-ins for one email from one IP. Rendered like the `throttle` middleware's 429 (same body and
 * rate-limit headers), so a client cannot tell this bucket from the per-IP one.
 */
class TooManyLoginAttempts extends RuntimeException implements ShouldntReport
{
    public function __construct(private readonly int $maxAttempts, private readonly int $retryAfterSeconds)
    {
        parent::__construct('Too Many Attempts.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Too Many Attempts.'], 429, [
            'Retry-After' => $this->retryAfterSeconds,
            'X-RateLimit-Reset' => now()->addSeconds($this->retryAfterSeconds)->getTimestamp(),
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}

<?php

namespace App\Tmdb\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * TMDB could not be reached or answered with an error. It is logged like any
 * exception, and rendered as a fixed 502 so TMDB's own error never reaches the client.
 */
class TmdbUnavailable extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('TMDB is unavailable.', 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Movie service is temporarily unavailable.'], 502);
    }
}

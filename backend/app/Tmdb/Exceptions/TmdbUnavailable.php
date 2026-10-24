<?php

namespace App\Tmdb\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * TMDB could not be reached or answered with an error. It is logged like any
 * exception, and rendered as a fixed 502 so TMDB's own error never reaches the client.
 *
 * The original HTTP exception is deliberately not chained: its message carries the full
 * request URL, which includes the API key, and the log would store it.
 */
class TmdbUnavailable extends RuntimeException
{
    public function __construct(string $message = 'TMDB is unavailable.')
    {
        parent::__construct($message);
    }

    /**
     * Describe a failed call using only its path and a message with the API key removed.
     */
    public static function fromFailure(string $path, Throwable $failure): self
    {
        $message = preg_replace('/(api_key=)[^&\s"\']+/i', '$1[redacted]', $failure->getMessage()) ?? '';

        $key = config('services.tmdb.key');

        if (is_string($key) && $key !== '') {
            $message = str_replace($key, '[redacted]', $message);
        }

        return new self("TMDB request to {$path} failed: {$message}");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Movie service is temporarily unavailable.'], 502);
    }
}

<?php

namespace App\MovieRecommendation\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The weather or Groq step failed. Rendered as a fixed 502 with a short message, so neither
 * Open-Meteo's nor Groq's own error ever reaches the client. The underlying failure is logged.
 */
class RecommendationUnavailable extends RuntimeException
{
    public static function weather(): self
    {
        return new self('Could not get the weather');
    }

    public static function generation(): self
    {
        return new self('Could not generate recommendations');
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => $this->getMessage()], 502);
    }
}

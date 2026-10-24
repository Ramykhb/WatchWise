<?php

namespace App\Weather\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Groq could not produce a weather mood. Rendered as a fixed 502; provider errors are only logged. */
class WeatherMoodUnavailable extends RuntimeException
{
    public function __construct(string $message = 'Could not generate the weather mood.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => 'Could not generate the weather mood'], 502);
    }
}

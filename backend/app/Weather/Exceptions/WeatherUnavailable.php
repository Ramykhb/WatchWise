<?php

namespace App\Weather\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** OpenWeatherMap could not be reached or answered with an error. Rendered as a fixed 502. */
class WeatherUnavailable extends RuntimeException
{
    public function __construct(string $message = 'OpenWeatherMap is unavailable.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Weather service is temporarily unavailable.'], 502);
    }
}

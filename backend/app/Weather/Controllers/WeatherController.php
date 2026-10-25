<?php

namespace App\Weather\Controllers;

use App\Weather\FormRequests\WeatherMoodRequest;
use App\Weather\FormRequests\WeatherRequest;
use App\Weather\InterfaceServices\WeatherMoodServiceInterface;
use App\Weather\InterfaceServices\WeatherServiceInterface;
use Illuminate\Http\JsonResponse;

class WeatherController
{
    public function __construct(
        private readonly WeatherServiceInterface $weather,
        private readonly WeatherMoodServiceInterface $moods,
    ) {}

    /**
     * Current weather for the given coordinates, or for the default location.
     */
    public function show(WeatherRequest $request): JsonResponse
    {
        return response()->json($this->weather->current($request->toDto()));
    }

    /**
     * The movie mood the given weather calls for.
     */
    public function mood(WeatherMoodRequest $request): JsonResponse
    {
        return response()->json($this->moods->mood($request->toDto()));
    }
}

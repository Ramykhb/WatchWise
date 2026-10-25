<?php

namespace App\MovieRecommendation\Controllers;

use App\MovieRecommendation\FormRequests\RecommendationRequest;
use App\MovieRecommendation\InterfaceServices\RecommendationServiceInterface;
use Illuminate\Http\JsonResponse;

class RecommendationController
{
    public function __construct(private readonly RecommendationServiceInterface $recommendations) {}

    /**
     * Ten movies that fit the current weather (default location unless coordinates are sent).
     */
    public function index(RecommendationRequest $request): JsonResponse
    {
        return response()->json($this->recommendations->forCurrentWeather($request->toDto()));
    }
}

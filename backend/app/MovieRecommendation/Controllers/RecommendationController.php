<?php

namespace App\MovieRecommendation\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\MovieRecommendation\FormRequests\RecommendationRequest;
use App\MovieRecommendation\InterfaceServices\FollowingRecommendationServiceInterface;
use App\MovieRecommendation\InterfaceServices\RecommendationServiceInterface;
use App\MovieRecommendation\Services\PeopleRecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController
{
    public function __construct(private readonly RecommendationServiceInterface $recommendations) {}

    public function watched(CurrentUser $current): JsonResponse
    {
        return response()->json($this->recommendations->forWatched($current->user()));
    }

    public function people(CurrentUser $current, PeopleRecommendationService $people): JsonResponse
    {
        return response()->json($people->forUser($current->user()));
    }

    public function following(CurrentUser $current, FollowingRecommendationServiceInterface $following): JsonResponse
    {
        return response()->json($following->forUser($current->user()));
    }

    /**
     * Ten movies that fit the current weather (default location unless coordinates are sent).
     */
    public function index(RecommendationRequest $request): JsonResponse
    {
        return response()->json($this->recommendations->forCurrentWeather($request->toDto()));
    }
}

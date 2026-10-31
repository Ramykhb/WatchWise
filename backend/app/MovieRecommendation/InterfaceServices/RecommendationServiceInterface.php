<?php

namespace App\MovieRecommendation\InterfaceServices;

use App\Models\User;
use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use App\MovieRecommendation\DTOs\Responses\RecommendationResponse;
use App\MovieRecommendation\Exceptions\RecommendationUnavailable;

interface RecommendationServiceInterface
{
    /** Six related titles based on a random watched movie or TV show. */
    public function forWatched(User $user): RecommendationResponse;

    /**
     * Ten movies that fit the current weather at the given location.
     *
     * @throws RecommendationUnavailable
     */
    public function forCurrentWeather(RecommendationQuery $query): RecommendationResponse;
}

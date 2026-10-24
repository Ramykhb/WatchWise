<?php

namespace App\MovieRecommendation\InterfaceServices;

use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use App\MovieRecommendation\DTOs\Responses\RecommendationResponse;
use App\MovieRecommendation\Exceptions\RecommendationUnavailable;

interface RecommendationServiceInterface
{
    /**
     * Ten movies that fit the current weather at the given location.
     *
     * @throws RecommendationUnavailable
     */
    public function forCurrentWeather(RecommendationQuery $query): RecommendationResponse;
}

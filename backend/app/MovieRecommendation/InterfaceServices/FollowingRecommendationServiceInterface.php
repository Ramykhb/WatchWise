<?php

namespace App\MovieRecommendation\InterfaceServices;

use App\Models\User;
use App\MovieRecommendation\DTOs\Responses\RecommendationResponse;

interface FollowingRecommendationServiceInterface
{
    public function forUser(User $user): RecommendationResponse;
}

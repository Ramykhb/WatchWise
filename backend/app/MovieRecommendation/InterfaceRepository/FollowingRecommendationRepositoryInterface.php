<?php

namespace App\MovieRecommendation\InterfaceRepository;

use App\Models\User;

interface FollowingRecommendationRepositoryInterface
{
    /** @return list<array{tmdb_id: int, media_type: string, following_count: int}> */
    public function candidates(User $viewer): array;
}

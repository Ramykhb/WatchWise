<?php

namespace App\MovieRecommendation\InterfaceRepository;

use App\Models\User;
use App\Support\HiddenMovies;

interface FollowingRecommendationRepositoryInterface
{
    /**
     * Titles that people the viewer follows rated five stars, most liked first, without the hidden movies.
     *
     * @return list<array{tmdb_id: int, media_type: string, following_count: int}>
     */
    public function candidates(User $viewer, HiddenMovies $hidden): array;
}

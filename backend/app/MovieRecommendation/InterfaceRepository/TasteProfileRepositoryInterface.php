<?php

namespace App\MovieRecommendation\InterfaceRepository;

use App\Models\User;

interface TasteProfileRepositoryInterface
{
    /** @return array{titles: array, five_star_ratings: int, following_count: int} */
    public function forUser(User $user): array;
}

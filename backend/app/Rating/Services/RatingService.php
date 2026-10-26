<?php

namespace App\Rating\Services;

use App\Models\User;
use App\Rating\InterfaceRepository\RatingRepositoryInterface;
use App\Rating\InterfaceServices\RatingServiceInterface;

class RatingService implements RatingServiceInterface
{
    public function __construct(private readonly RatingRepositoryInterface $ratings) {}

    public function forMovie(User $user, int $tmdbId): ?int
    {
        return $this->ratings->find($user, $tmdbId);
    }

    public function rate(User $user, int $tmdbId, int $rating): int
    {
        $this->ratings->save($user, $tmdbId, $rating);

        return $rating;
    }
}

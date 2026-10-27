<?php

namespace App\Rating\Services;

use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Rating\InterfaceRepository\RatingRepositoryInterface;
use App\Rating\InterfaceServices\RatingServiceInterface;
use App\Support\MediaType;

class RatingService implements RatingServiceInterface
{
    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
        private readonly MovieCatalogServiceInterface $catalog,
    ) {}

    public function forMovie(User $user, int $tmdbId, string $mediaType = MediaType::MOVIE): ?int
    {
        return $this->ratings->find($user, $tmdbId, $mediaType);
    }

    public function rate(User $user, int $tmdbId, int $rating, string $mediaType = MediaType::MOVIE): int
    {
        $this->ratings->save($user, $tmdbId, $mediaType, $rating);
        // The local catalogue (admin "Movie data") only holds movies.
        if ($mediaType === MediaType::MOVIE) {
            $this->catalog->remember($tmdbId);
        }

        return $rating;
    }
}

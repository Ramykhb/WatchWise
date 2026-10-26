<?php

namespace App\Person\InterfaceRepository;

use App\Person\Exceptions\PersonNotFound;
use App\Support\Exceptions\TmdbUnavailable;

/**
 * Reads people from TMDB. Returns TMDB's decoded payload; the service turns it into DTOs.
 */
interface PersonRepositoryInterface
{
    /**
     * One person with their movie credits (cast and crew).
     *
     * @return array<string, mixed>
     *
     * @throws PersonNotFound
     * @throws TmdbUnavailable
     */
    public function find(int $id): array;

    /**
     * One movie's cast and crew (both empty when TMDB no longer has the movie).
     *
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function movieCredits(int $movieId): array;
}

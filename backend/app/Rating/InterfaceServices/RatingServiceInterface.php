<?php

namespace App\Rating\InterfaceServices;

use App\Models\User;

interface RatingServiceInterface
{
    /** The member's stars for the title (a movie or a TV show), or null. */
    public function forMovie(User $user, int $tmdbId, string $mediaType = 'movie'): ?int;

    /** Saves the stars (1–5) and returns them. */
    public function rate(User $user, int $tmdbId, int $rating, string $mediaType = 'movie'): int;
}

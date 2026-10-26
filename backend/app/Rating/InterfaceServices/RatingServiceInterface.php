<?php

namespace App\Rating\InterfaceServices;

use App\Models\User;

interface RatingServiceInterface
{
    /** The member's stars for the film, or null. */
    public function forMovie(User $user, int $tmdbId): ?int;

    /** Saves the stars (1–5) and returns them. */
    public function rate(User $user, int $tmdbId, int $rating): int;
}

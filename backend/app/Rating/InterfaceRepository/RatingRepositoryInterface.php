<?php

namespace App\Rating\InterfaceRepository;

use App\Models\User;

interface RatingRepositoryInterface
{
    /** The member's stars for the title (1–5), or null when they haven't rated it. */
    public function find(User $user, int $tmdbId, string $mediaType): ?int;

    /**
     * Saves the stars in one statement: a new rating-only review, or just the stars of the
     * member's existing review (its text and approval stay as they are).
     */
    public function save(User $user, int $tmdbId, string $mediaType, int $rating): void;
}

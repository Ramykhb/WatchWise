<?php

namespace App\Rating\Repository;

use App\Models\Review;
use App\Models\User;
use App\Rating\InterfaceRepository\RatingRepositoryInterface;

class RatingRepository implements RatingRepositoryInterface
{
    public function find(User $user, int $tmdbId, string $mediaType): ?int
    {
        $rating = $user->reviews()->where('tmdb_id', $tmdbId)->where('media_type', $mediaType)->value('rating');

        return $rating === null ? null : (int) $rating;
    }

    public function save(User $user, int $tmdbId, string $mediaType, int $rating): void
    {
        // Upsert on (user_id, tmdb_id, media_type), so two quick clicks can't create two rows. A rating with no
        // text has nothing to moderate, so a new row is approved; an existing review keeps its state.
        Review::upsert(
            [[
                'user_id' => $user->id,
                'tmdb_id' => $tmdbId,
                'media_type' => $mediaType,
                'rating' => $rating,
                'content' => null,
                'sentiment' => null,
                'is_approved' => true,
            ]],
            ['user_id', 'tmdb_id', 'media_type'],
            ['rating', 'updated_at'],
        );
    }
}

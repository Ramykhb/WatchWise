<?php

namespace App\MovieRecommendation\Repository;

use App\Models\User;
use App\MovieRecommendation\InterfaceRepository\TasteProfileRepositoryInterface;

class TasteProfileRepository implements TasteProfileRepositoryInterface
{
    public function forUser(User $user): array
    {
        return [
            'titles' => $user->watchlists()->where('status', 'watched')->get(['tmdb_id', 'media_type'])
                ->map(fn ($entry): array => ['id' => (int) $entry->tmdb_id, 'media_type' => $entry->media_type])->all(),
            'five_star_ratings' => $user->reviews()->where('rating', 5)->count(),
            'following_count' => $user->following()->where('is_active', true)->count(),
        ];
    }
}

<?php

namespace App\Watchlist\Repository;

use App\Models\User;
use App\Models\Watchlist;
use App\Support\MediaType;
use App\Watchlist\InterfaceRepository\WatchlistRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class WatchlistRepository implements WatchlistRepositoryInterface
{
    public function forUser(User $user): Collection
    {
        return $user->watchlists()->latest()->get();
    }

    public function ratingsByTmdbId(User $user): SupportCollection
    {
        // Keyed like the frontend names titles ("550", "tv-1399"), since a movie and a show can share an id.
        return $user->reviews()->get(['tmdb_id', 'media_type', 'rating'])
            ->mapWithKeys(fn ($review) => [MediaType::key($review->media_type ?? MediaType::MOVIE, $review->tmdb_id) => $review->rating]);
    }

    public function findOrNew(User $user, int $tmdbId, string $mediaType): Watchlist
    {
        return $user->watchlists()->firstOrNew(['tmdb_id' => $tmdbId, 'media_type' => $mediaType]);
    }

    public function save(Watchlist $item): void
    {
        $item->save();
    }

    public function delete(Watchlist $item): void
    {
        $item->delete();
    }
}

<?php

namespace App\Watchlist\Repository;

use App\Models\User;
use App\Models\Watchlist;
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
        return $user->reviews()->pluck('rating', 'tmdb_id');
    }

    public function findOrNew(User $user, int $tmdbId): Watchlist
    {
        return $user->watchlists()->firstOrNew(['tmdb_id' => $tmdbId]);
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

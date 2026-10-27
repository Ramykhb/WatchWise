<?php

namespace App\Watchlist\InterfaceRepository;

use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface WatchlistRepositoryInterface
{
    /**
     * @return Collection<int, Watchlist> the user's rows, newest first
     */
    public function forUser(User $user): Collection;

    /**
     * @return SupportCollection<string, int> the user's stars by title key ("550" for a movie, "tv-1399" for a show)
     */
    public function ratingsByTmdbId(User $user): SupportCollection;

    /** The user's row for this film, or an unsaved one when it is not in the watchlist yet. */
    public function findOrNew(User $user, int $tmdbId, string $mediaType): Watchlist;

    public function save(Watchlist $item): void;

    public function delete(Watchlist $item): void;
}

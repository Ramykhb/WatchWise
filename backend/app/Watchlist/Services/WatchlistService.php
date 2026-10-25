<?php

namespace App\Watchlist\Services;

use App\ActivityFeed\Services\ActivityFeedService;
use App\Models\User;
use App\Models\Watchlist;
use App\Watchlist\DTOs\Requests\AddToWatchlistData;
use App\Watchlist\DTOs\Requests\UpdateWatchlistStatusData;
use App\Watchlist\DTOs\Responses\AddToWatchlistResponse;
use App\Watchlist\DTOs\Responses\WatchlistItemResponse;
use App\Watchlist\DTOs\Responses\WatchlistResponse;
use App\Watchlist\InterfaceRepository\WatchlistRepositoryInterface;
use App\Watchlist\InterfaceServices\WatchlistServiceInterface;

class WatchlistService implements WatchlistServiceInterface
{
    public function __construct(
        private readonly WatchlistRepositoryInterface $watchlist,
        private readonly ActivityFeedService $activity,
    ) {}

    public function overview(User $user): WatchlistResponse
    {
        return new WatchlistResponse(
            $this->watchlist->forUser($user)->map(fn (Watchlist $item) => WatchlistItemResponse::fromModel($item))->values()->all(),
            $this->watchlist->ratingsByTmdbId($user),
        );
    }

    public function add(User $user, AddToWatchlistData $data): AddToWatchlistResponse
    {
        $item = $this->watchlist->findOrNew($user, $data->tmdbId);
        $isNew = ! $item->exists;
        $item->status = $data->status ?? $item->status ?? 'to_watch';
        $item->watched_at = $item->status === 'watched' ? ($item->watched_at ?? now()) : null;
        $this->watchlist->save($item);

        if ($isNew) {
            $this->activity->record($user, 'watchlist_add', $item, $item->tmdb_id, $user->shares('watchlist'));
        }

        return new AddToWatchlistResponse(WatchlistItemResponse::fromModel($item), $isNew);
    }

    public function updateStatus(Watchlist $item, UpdateWatchlistStatusData $data): WatchlistItemResponse
    {
        $item->status = $data->status;
        $item->watched_at = $data->status === 'watched' ? now() : null;
        $this->watchlist->save($item);

        return WatchlistItemResponse::fromModel($item);
    }

    public function remove(Watchlist $item): void
    {
        $this->watchlist->delete($item);
    }
}

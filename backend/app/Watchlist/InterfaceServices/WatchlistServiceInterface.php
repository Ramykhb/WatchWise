<?php

namespace App\Watchlist\InterfaceServices;

use App\Models\User;
use App\Models\Watchlist;
use App\Watchlist\DTOs\Requests\AddToWatchlistData;
use App\Watchlist\DTOs\Requests\UpdateWatchlistStatusData;
use App\Watchlist\DTOs\Responses\AddToWatchlistResponse;
use App\Watchlist\DTOs\Responses\WatchlistItemResponse;
use App\Watchlist\DTOs\Responses\WatchlistResponse;

interface WatchlistServiceInterface
{
    public function overview(User $user): WatchlistResponse;

    /** Adds the film, or changes the status of the one already there. */
    public function add(User $user, AddToWatchlistData $data): AddToWatchlistResponse;

    /** Marking a film `watched` stamps `watched_at`; any other status clears it. */
    public function updateStatus(Watchlist $item, UpdateWatchlistStatusData $data): WatchlistItemResponse;

    public function remove(Watchlist $item): void;
}

<?php

namespace App\Watchlist\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Watchlist;

class WatchlistPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, Watchlist $watchlist): bool
    {
        return $this->owns($current, $watchlist);
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, Watchlist $watchlist): bool
    {
        return $this->owns($current, $watchlist);
    }

    public function delete(CurrentUser $current, Watchlist $watchlist): bool
    {
        return $this->owns($current, $watchlist);
    }

    private function owns(CurrentUser $current, Watchlist $watchlist): bool
    {
        return $current->isAuthenticated() && $current->id() === $watchlist->user_id;
    }
}

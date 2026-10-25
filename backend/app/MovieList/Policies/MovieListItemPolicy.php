<?php

namespace App\MovieList\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieListItem;

/**
 * Items inherit permissions from their parent list.
 */
class MovieListItemPolicy
{
    public function __construct(private readonly MovieListPolicy $lists) {}

    public function view(CurrentUser $current, MovieListItem $item): bool
    {
        return $this->lists->view($current, $item->movieList);
    }

    public function create(CurrentUser $current, MovieListItem $item): bool
    {
        return $this->lists->update($current, $item->movieList);
    }

    public function update(CurrentUser $current, MovieListItem $item): bool
    {
        return $this->lists->update($current, $item->movieList);
    }

    public function delete(CurrentUser $current, MovieListItem $item): bool
    {
        return $this->lists->update($current, $item->movieList);
    }
}

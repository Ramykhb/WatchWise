<?php

namespace App\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;

class MovieListPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return true;
    }

    public function view(CurrentUser $current, MovieList $movieList): bool
    {
        return $movieList->is_public || $this->owns($current, $movieList) || $current->isAdmin();
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, MovieList $movieList): bool
    {
        return $this->owns($current, $movieList);
    }

    public function delete(CurrentUser $current, MovieList $movieList): bool
    {
        return $this->owns($current, $movieList) || $current->isAdmin();
    }

    private function owns(CurrentUser $current, MovieList $movieList): bool
    {
        return $current->isAuthenticated() && $current->id() === $movieList->user_id;
    }
}

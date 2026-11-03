<?php

namespace App\MovieList\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;

class MovieListPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return true;
    }

    /**
     * A public list is open to everyone, unless its owner's profile is followers-only or private: then only to
     * their followers. The owner and admins always see it.
     */
    public function view(CurrentUser $current, MovieList $movieList): bool
    {
        return $this->owns($current, $movieList)
            || $current->isAdmin()
            || ($movieList->is_public && $this->ownerSharesWith($current, $movieList));
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

    private function ownerSharesWith(CurrentUser $current, MovieList $movieList): bool
    {
        $owner = $movieList->user;

        return $owner !== null && ($owner->profile_visibility === 'public' || $owner->isFollowedBy($current->id()));
    }
}

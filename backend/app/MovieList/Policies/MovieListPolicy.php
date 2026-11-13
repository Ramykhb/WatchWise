<?php

namespace App\MovieList\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;
use App\User\Services\ProfilePrivacy;

class MovieListPolicy
{
    public function __construct(private readonly ProfilePrivacy $privacy) {}

    public function viewAny(CurrentUser $current): bool
    {
        return true;
    }

    /**
     * Someone else's list is shown when it is public, its owner's account is active and the owner's profile
     * lets this viewer see their lists (profile visibility + the Settings > Privacy "lists" switch).
     * The owner and admins always see it.
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

    /** Members report other members' lists they can see. */
    public function report(CurrentUser $current, MovieList $movieList): bool
    {
        return $current->isAuthenticated() && ! $this->owns($current, $movieList) && $this->view($current, $movieList);
    }

    private function owns(CurrentUser $current, MovieList $movieList): bool
    {
        return $current->isAuthenticated() && $current->id() === $movieList->user_id;
    }

    private function ownerSharesWith(CurrentUser $current, MovieList $movieList): bool
    {
        $owner = $movieList->user;

        return $owner !== null && $owner->is_active && $this->privacy->shows($owner, $current, 'lists');
    }
}

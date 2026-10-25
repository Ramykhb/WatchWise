<?php

namespace App\MovieNight\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieNight;

class MovieNightPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    /** Visible to the organizer and invited users. */
    public function view(CurrentUser $current, MovieNight $movieNight): bool
    {
        if (! $current->isAuthenticated()) {
            return false;
        }

        return $this->organizes($current, $movieNight)
            || $movieNight->invites()->where('user_id', $current->id())->exists();
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, MovieNight $movieNight): bool
    {
        return $this->organizes($current, $movieNight);
    }

    public function delete(CurrentUser $current, MovieNight $movieNight): bool
    {
        return $this->organizes($current, $movieNight);
    }

    private function organizes(CurrentUser $current, MovieNight $movieNight): bool
    {
        return $current->isAuthenticated() && $current->id() === $movieNight->organizer_id;
    }
}

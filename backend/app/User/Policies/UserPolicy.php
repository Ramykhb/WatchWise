<?php

namespace App\User\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;

class UserPolicy
{
    /** Private profiles are visible only to their owner and admins; "followers" profiles also to followers. */
    public function view(CurrentUser $current, User $profile): bool
    {
        return $profile->profile_visibility === 'public'
            || $this->isSelf($current, $profile)
            || $current->isAdmin()
            || ($profile->profile_visibility === 'followers' && $profile->isFollowedBy($current->id()));
    }

    /** Anyone signed in can follow someone else. */
    public function follow(CurrentUser $current, User $profile): bool
    {
        return $current->isAuthenticated() && ! $this->isSelf($current, $profile);
    }

    public function update(CurrentUser $current, User $profile): bool
    {
        return $this->isSelf($current, $profile);
    }

    public function delete(CurrentUser $current, User $profile): bool
    {
        return $this->isSelf($current, $profile) || $current->isAdmin();
    }

    private function isSelf(CurrentUser $current, User $profile): bool
    {
        return $current->isAuthenticated() && $current->id() === $profile->id;
    }
}

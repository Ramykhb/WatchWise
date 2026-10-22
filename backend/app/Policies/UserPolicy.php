<?php

namespace App\Policies;

use App\Contracts\CurrentUser;
use App\Models\User;

class UserPolicy
{
    /** Private profiles are visible only to their owner and admins. */
    public function view(CurrentUser $current, User $profile): bool
    {
        return $profile->profile_visibility === 'public'
            || $this->isSelf($current, $profile)
            || $current->isAdmin();
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

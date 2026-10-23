<?php

namespace App\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\UserPreference;

class UserPreferencePolicy
{
    public function view(CurrentUser $current, UserPreference $preference): bool
    {
        return $this->owns($current, $preference);
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, UserPreference $preference): bool
    {
        return $this->owns($current, $preference);
    }

    public function delete(CurrentUser $current, UserPreference $preference): bool
    {
        return $this->owns($current, $preference);
    }

    private function owns(CurrentUser $current, UserPreference $preference): bool
    {
        return $current->isAuthenticated() && $current->id() === $preference->user_id;
    }
}

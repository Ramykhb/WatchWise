<?php

namespace App\Admin\Policies;

use App\Auth\CurrentUser\CurrentUser;

/**
 * The admin area's abilities. They belong to no single model, so AuthorizationServiceProvider
 * defines them as gates. The `role:admin` route middleware is the first line; these are the second.
 */
class AdminPolicy
{
    /** Moderators may read the dashboard statistics. */
    public function viewDashboard(CurrentUser $current): bool
    {
        return $this->isStaff($current);
    }

    /** Listing users and suspending plain members; the service limits what a moderator may change. */
    public function viewUsers(CurrentUser $current): bool
    {
        return $this->isStaff($current);
    }

    /** Deleting accounts and changing roles stay with admins. */
    public function manageUsers(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }

    public function moderateReviews(CurrentUser $current): bool
    {
        return $this->isStaff($current);
    }

    public function manageMovies(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }

    private function isStaff(CurrentUser $current): bool
    {
        return $current->isAdmin() || $current->role() === 'moderator';
    }
}

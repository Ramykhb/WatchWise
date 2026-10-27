<?php

namespace App\Admin\Policies;

use App\Auth\CurrentUser\CurrentUser;

/**
 * The admin area's abilities. They belong to no single model, so AuthorizationServiceProvider
 * defines them as gates. The `role:admin` route middleware is the first line; these are the second.
 */
class AdminPolicy
{
    public function viewDashboard(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }

    public function manageUsers(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }

    public function moderateReviews(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }

    public function manageMovies(CurrentUser $current): bool
    {
        return $current->isAdmin();
    }
}

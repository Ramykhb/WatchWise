<?php

namespace App\Policies;

use App\Contracts\CurrentUser;
use App\Models\Review;

class ReviewPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return true;
    }

    public function view(CurrentUser $current, Review $review): bool
    {
        return $review->is_approved || $this->owns($current, $review) || $current->isAdmin();
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, Review $review): bool
    {
        return $this->owns($current, $review);
    }

    public function delete(CurrentUser $current, Review $review): bool
    {
        return $this->owns($current, $review) || $current->isAdmin();
    }

    private function owns(CurrentUser $current, Review $review): bool
    {
        return $current->isAuthenticated() && $current->id() === $review->user_id;
    }
}

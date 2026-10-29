<?php

namespace App\Policies;

use App\Auth\CurrentUser\CurrentUser;
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

    /** Members report published reviews by others; their own, unpublished and removed ones can't be reported. */
    public function report(CurrentUser $current, Review $review): bool
    {
        return $current->isAuthenticated()
            && ! $this->owns($current, $review)
            && $review->is_approved
            && $review->removed_at === null
            && $review->content !== null
            && $review->content !== '';
    }

    private function owns(CurrentUser $current, Review $review): bool
    {
        return $current->isAuthenticated() && $current->id() === $review->user_id;
    }
}

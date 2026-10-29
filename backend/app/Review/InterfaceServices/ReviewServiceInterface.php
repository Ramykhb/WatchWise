<?php

namespace App\Review\InterfaceServices;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewServiceInterface
{
    /**
     * The reviews an administrator or moderator can moderate (see `ReviewRepositoryInterface::paginateForModeration()`).
     *
     * @param  'pending'|'approved'|'flagged'|'removed'  $status
     * @return LengthAwarePaginator<int, Review>
     */
    public function moderationQueue(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator;

    /**
     * @return array{flagged: int, removed: int, sentiment: array{positive: int, neutral: int, negative: int}}
     */
    public function moderationTotals(): array;

    /** Makes the review visible to everyone and closes its reports. */
    public function approve(Review $review): Review;

    /** Rejects the text; the member's star rating stays. */
    public function reject(Review $review): Review;

    public function delete(Review $review): void;
}

<?php

namespace App\Review\InterfaceRepository;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * Reviews to moderate, newest first, with their author and reports. Statuses (a review has exactly one):
     * `removed` (a moderator removed the text; these rows have none), `flagged` (text with unresolved reports),
     * `approved` and `pending` (text without unresolved reports).
     *
     * @param  'pending'|'approved'|'flagged'|'removed'  $status
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateForModeration(string $status, int $page, int $perPage): LengthAwarePaginator;

    /**
     * Site-wide moderation numbers: flagged and removed reviews, and the sentiment of the reviews with text that still stand.
     *
     * @return array{flagged: int, removed: int, sentiment: array{positive: int, neutral: int, negative: int}}
     */
    public function moderationTotals(): array;

    /** Approves the review and closes its open reports. */
    public function markApproved(Review $review): Review;

    /** Removes the text and sentiment but keeps the star rating, marks the review removed and closes its open reports. */
    public function clearText(Review $review): Review;

    public function delete(Review $review): void;
}

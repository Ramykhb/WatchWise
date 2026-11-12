<?php

namespace App\Review\InterfaceRepository;

use App\Models\Review;
use App\Models\User;
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
    public function paginateForModeration(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator;

    /**
     * Site-wide moderation numbers: flagged and removed reviews, and the sentiment of the reviews with text that still stand.
     *
     * @return array{flagged: int, removed: int, sentiment: array{positive: int, neutral: int, negative: int}}
     */
    public function moderationTotals(): array;

    /** A fresh copy of the review, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $review): Review;

    /** How many reports on the review are still open. */
    public function openReportsCount(Review $review): int;

    /** Approves the review. */
    public function markApproved(Review $review): Review;

    /** Removes the text and sentiment but keeps the star rating, marks the review removed. */
    public function clearText(Review $review): Review;

    /** Deletes the review with every report filed against it. */
    public function delete(Review $review): void;

    /**
     * What the user wrote and rated, in one query: reviews with text, and rows carrying stars (a rating-only row or a review with stars).
     *
     * @return array{reviews_with_text: int, ratings: int}
     */
    public function countsForUser(User $user): array;
}

<?php

namespace App\Review\InterfaceServices;

use App\Models\Review;
use App\Models\User;
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

    /** A fresh copy of the review, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $review): Review;

    /** How many reports on the review are still open; approving or rejecting it closes them all. */
    public function openReportsCount(Review $review): int;

    /** Makes the review visible to everyone and dismisses its open reports, closed by `$moderator`. */
    public function approve(Review $review, User $moderator): Review;

    /** Rejects the text (the member's star rating stays) and resolves its open reports, closed by `$moderator`. */
    public function reject(Review $review, User $moderator): Review;

    public function delete(Review $review): void;

    /**
     * How many reviews with text the member wrote and how many titles they rated with stars (a star rating without text counts too).
     *
     * @return array{reviews_with_text: int, ratings: int}
     */
    public function countsForUser(User $user): array;
}

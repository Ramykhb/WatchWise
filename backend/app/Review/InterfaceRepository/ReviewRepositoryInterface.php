<?php

namespace App\Review\InterfaceRepository;

use App\Models\Review;
use App\Models\User;
use App\Review\DTOs\Requests\ModerationQueueQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * Reviews to moderate, newest first, with their author and reports. Statuses (a review has exactly one):
     * `removed` (a moderator removed the text; these rows have none), `flagged` (text with unresolved reports),
     * `approved` and `pending` (text without unresolved reports). The query's author and title filters narrow the
     * queue like its search, sentiment and reason filters do.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateForModeration(ModerationQueueQuery $query): LengthAwarePaginator;

    /**
     * Site-wide moderation numbers: flagged and removed reviews, and the sentiment of the reviews with text that still stand.
     *
     * @return array{flagged: int, removed: int, sentiment: array{positive: int, neutral: int, negative: int}}
     */
    public function moderationTotals(): array;

    /**
     * The reviews with these ids that still exist, keyed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Review>
     */
    public function findMany(array $ids): Collection;

    /** A fresh copy of the review, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $review): Review;

    /** How many reports on the review are still open. */
    public function openReportsCount(Review $review): int;

    /** How many members marked the review as helpful. */
    public function helpfulVotesCount(Review $review): int;

    /**
     * What staff weigh up about a review's author: their reviews with text, their reviews a moderator removed and
     * the open reports against any of their reviews.
     *
     * @return array{reviews_with_text: int, removed_reviews: int, open_reports: int}
     */
    public function authorModerationActivity(int $userId): array;

    /** Approves the review. */
    public function markApproved(Review $review): Review;

    /** Removes the text and sentiment but keeps the star rating, marks the review removed. A review that is already removed is left as it is. */
    public function clearText(Review $review): Review;

    /** Sets whether the review's text contains spoilers. */
    public function setSpoilers(Review $review, bool $hasSpoilers): Review;

    /** Deletes the review with every report filed against it. */
    public function delete(Review $review): void;

    /**
     * What the user wrote and rated, in one query: reviews with text, and rows carrying stars (a rating-only row or a review with stars).
     *
     * @return array{reviews_with_text: int, ratings: int}
     */
    public function countsForUser(User $user): array;
}

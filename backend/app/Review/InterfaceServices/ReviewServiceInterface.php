<?php

namespace App\Review\InterfaceServices;

use App\Models\Review;
use App\Models\User;
use App\Review\DTOs\Requests\ModerationQueueQuery;
use App\Review\Exceptions\ReviewHasNoText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewServiceInterface
{
    /**
     * The reviews an administrator or moderator can moderate (see `ReviewRepositoryInterface::paginateForModeration()`).
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function moderationQueue(ModerationQueueQuery $query): LengthAwarePaginator;

    /**
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

    /**
     * What staff see next to a review: how many members found it helpful (the app has no down-votes) and the
     * author's moderation record, in a constant number of queries.
     *
     * @return array{helpful_votes: int, author: array{reviews_with_text: int, removed_reviews: int, open_reports: int}}
     */
    public function moderationContext(Review $review): array;

    /** A fresh copy of the review, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $review): Review;

    /** How many reports on the review are still open; approving or rejecting it closes them all. */
    public function openReportsCount(Review $review): int;

    /** Makes the review visible to everyone and dismisses its open reports, closed by `$moderator`. */
    public function approve(Review $review, User $moderator): Review;

    /** Rejects the text (the member's star rating stays) and resolves its open reports, closed by `$moderator`. */
    public function reject(Review $review, User $moderator): Review;

    /**
     * Marks the review's text as containing spoilers or not (the member's own flag is replaced).
     * Call it with a row-locked copy, inside the transaction that records the change.
     *
     * A review without text (a plain star rating, or one whose text was removed) has no spoilers by definition:
     * clearing the flag on it changes nothing, setting it is refused.
     *
     * @throws ReviewHasNoText when the flag is set on a review without text
     */
    public function changeSpoilers(Review $review, bool $hasSpoilers): Review;

    public function delete(Review $review): void;
}

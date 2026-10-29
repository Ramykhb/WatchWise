<?php

namespace App\Review\InterfaceRepository;

use App\Models\Review;
use App\Models\User;
use App\Review\DTOs\Requests\MovieReviewQuery;
use App\Review\DTOs\Requests\SaveReviewData;
use Illuminate\Database\Eloquent\Collection;

/** A title's reviews as the Movie page shows them. A review is a row with text; rating-only rows are star ratings. */
interface MovieReviewRepositoryInterface
{
    /**
     * Approved reviews with text by other members whose privacy lets the viewer see them, one page
     * plus one extra (to tell whether more exist), with helpful counts and the viewer's vote.
     *
     * @return Collection<int, Review>
     */
    public function page(MovieReviewQuery $query, User $viewer): Collection;

    /** The member's own review with text (approved or not), with its helpful count. */
    public function mine(User $user, int $tmdbId, string $mediaType): ?Review;

    /**
     * Stars from every approved rating and review of the title, and the sentiment of its approved reviews.
     *
     * @return array{average: float|null, ratings: int, stars: array<int, int>, reviews: int, sentiment: array<string, int>}
     */
    public function summary(int $tmdbId, string $mediaType): array;

    /** Whether the review's author lets the viewer read their reviews (same rules as the review list). */
    public function authorVisibleTo(Review $review, User $viewer): bool;

    /** Writes the classified review and clears its old helpful votes and reports. @param list<string>|null $labels */
    public function save(User $user, SaveReviewData $data, string $sentiment, ?array $labels, bool $approved, bool $hasSpoilers): Review;

    /** Removes the text and its helpful votes; the member's stars stay. */
    public function clearText(User $user, int $tmdbId, string $mediaType): void;

    /**
     * Stores the member's report of the review, or changes the reason of the one they already filed.
     *
     * @return bool true when the report is new
     */
    public function saveReport(Review $review, User $reporter, string $reason): bool;

    /** Marks the review as helpful for the member; doing it twice is harmless. */
    public function vote(Review $review, User $user): void;

    public function unvote(Review $review, User $user): void;

    public function votesCount(Review $review): int;
}

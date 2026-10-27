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

    /** Writes the review (and so the member's stars); it waits for moderation again. */
    public function save(User $user, SaveReviewData $data, string $sentiment): Review;

    /** Removes the text and its helpful votes; the member's stars stay. */
    public function clearText(User $user, int $tmdbId, string $mediaType): void;

    /** Marks the review as helpful for the member; doing it twice is harmless. */
    public function vote(Review $review, User $user): void;

    public function unvote(Review $review, User $user): void;

    public function votesCount(Review $review): int;
}

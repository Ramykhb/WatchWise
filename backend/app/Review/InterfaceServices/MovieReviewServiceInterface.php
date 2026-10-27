<?php

namespace App\Review\InterfaceServices;

use App\Models\Review;
use App\Models\User;
use App\Review\DTOs\Requests\MovieReviewQuery;
use App\Review\DTOs\Requests\SaveReviewData;
use App\Review\DTOs\Responses\MovieReviewResponse;

/** Reviews on the Movie page: read a title's reviews, write your own, and mark others' as helpful. */
interface MovieReviewServiceInterface
{
    /**
     * The summary (average, star distribution and sentiment in percent), your own review and one page
     * of other members' reviews.
     *
     * @return array{summary: array<string, mixed>, mine: MovieReviewResponse|null, reviews: list<MovieReviewResponse>, has_more: bool}
     */
    public function forTitle(MovieReviewQuery $query, User $viewer): array;

    /** Writes or edits the member's review (its stars become their rating) and classifies its sentiment. */
    public function save(User $user, SaveReviewData $data): MovieReviewResponse;

    /** Deletes the review's text; the member's star rating stays. */
    public function delete(User $user, int $tmdbId, string $mediaType): void;

    /** @return int the review's helpful count after the vote */
    public function markHelpful(Review $review, User $user, bool $helpful): int;
}

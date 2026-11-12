<?php

namespace App\Rating\InterfaceServices;

use App\Models\Review;
use App\Models\User;
use App\Rating\DTOs\Requests\RatingBurstQuery;
use App\Rating\DTOs\Requests\RatingQuery;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A star rating is a `reviews` row without text. The member-facing methods read and save the signed-in member's own
 * stars; the others serve staff and only ever see or touch rows without text, so a review is never reached through them.
 */
interface RatingServiceInterface
{
    /** The member's stars for the title (a movie or a TV show), or null. */
    public function forMovie(User $user, int $tmdbId, string $mediaType = 'movie'): ?int;

    /** Saves the stars (1–5) and returns them. */
    public function rate(User $user, int $tmdbId, int $rating, string $mediaType = 'movie'): int;

    /**
     * Star-only ratings matching the filters, newest first, each with its `user` (id, name, username) loaded.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function page(RatingQuery $query): LengthAwarePaginator;

    /**
     * Count, average and star distribution of every star-only rating the filters match (not just one page).
     * `distribution` always has all five star values; `average` is null without ratings, else rounded to two decimals.
     *
     * @return array{total: int, average: float|null, distribution: array<int, int>}
     */
    public function summary(RatingQuery $query): array;

    /**
     * One title's picture: the `summary()` of its star-only ratings, `with_text_count` (reviews with text, which are
     * not counted in the rest) and when its first and last star-only rating was left.
     *
     * @return array{total: int, average: float|null, distribution: array<int, int>, with_text_count: int, first_rated_at: CarbonInterface|null, last_rated_at: CarbonInterface|null}
     */
    public function filmPicture(int $tmdbId, string $mediaType): array;

    /**
     * Members who left at least `$query->min` star ratings in the `$query->hours` hours up to `$until`, judged by
     * when each rating was first created (changing the stars of an old rating does not count). Most ratings first.
     * Each row is a `Review` with the member's `user_id`, their `user` loaded and the count in its `total` attribute.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function bursts(RatingBurstQuery $query, CarbonInterface $until): LengthAwarePaginator;

    /** A fresh copy of the row, locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $rating): Review;

    /**
     * Deletes a star-only rating and the reports filed against it, and returns how many reports went. The caller
     * makes sure it has no text and was not moderated.
     */
    public function remove(Review $rating): int;

    /**
     * Deletes every star-only rating of the member that no moderator touched (`removed_at` null); their reviews
     * with text and the rows of moderated reviews stay. Call inside a transaction.
     *
     * @return array{ratings: int, reports: int} how many ratings and how many reports it deleted
     */
    public function removeAllOf(User $member): array;
}

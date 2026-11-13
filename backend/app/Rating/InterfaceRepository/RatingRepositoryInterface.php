<?php

namespace App\Rating\InterfaceRepository;

use App\Models\Review;
use App\Models\User;
use App\Rating\DTOs\Requests\RatingQuery;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface RatingRepositoryInterface
{
    /** The member's stars for the title (1–5), or null when they haven't rated it. */
    public function find(User $user, int $tmdbId, string $mediaType): ?int;

    /**
     * Saves the stars in one statement: a new rating-only review, or just the stars of the
     * member's existing review (its text and approval stay as they are).
     */
    public function save(User $user, int $tmdbId, string $mediaType, int $rating): void;

    /**
     * Star-only ratings (rows without text) matching the filters, newest first with ties broken by id, each with
     * its `user` (id, name, username) loaded.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginate(RatingQuery $query): LengthAwarePaginator;

    /**
     * How many star-only ratings the filters match for each star value. Page and page size are ignored.
     *
     * @return array<int, int> count keyed by star value; values nobody gave are absent
     */
    public function starCounts(RatingQuery $query): array;

    /** How many reviews with text the title has. */
    public function withTextCount(int $tmdbId, string $mediaType): int;

    /**
     * When the title's first and last star-only rating was left; both null without any.
     *
     * @return array{first: CarbonInterface|null, last: CarbonInterface|null}
     */
    public function ratedRange(int $tmdbId, string $mediaType): array;

    /**
     * Members with at least `$min` star-only ratings created from `$since` up to `$until`, most ratings first and
     * lowest member id first among equals. Each row is a `Review` carrying the member's `user_id`, their `user`
     * (id, name, username, created_at) and the number of ratings in the `total` attribute.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateBursts(CarbonInterface $since, CarbonInterface $until, int $min, int $page, int $perPage): LengthAwarePaginator;

    /** How many star-only ratings were created from `$from` (inclusive) until `$until` (exclusive); no end when it is null. */
    public function countCreated(CarbonInterface $from, ?CarbonInterface $until): int;

    /** A fresh copy of the row, locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Review $rating): Review;

    /** Deletes the star-only rating together with every report filed against it, and returns how many reports went. */
    public function delete(Review $rating): int;

    /**
     * Deletes every star-only rating of the member that no moderator touched (`removed_at` null), with the reports
     * filed against them; rows with text and rows of a moderated review stay. Call inside a transaction: the rows
     * are locked before they are deleted.
     *
     * @return array{ratings: int, reports: int} how many ratings and how many reports it deleted
     */
    public function deleteAllOf(User $member): array;
}

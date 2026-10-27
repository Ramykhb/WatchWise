<?php

namespace App\Admin\InterfaceRepository;

use Carbon\CarbonInterface;

/**
 * Aggregate queries for the admin dashboard. A review here is a row with text; rating-only rows are star ratings.
 * The movie rankings and genre weights count movies only: a TV show can share a movie's TMDB id.
 */
interface AdminStatsRepositoryInterface
{
    public function usersTotal(): int;

    public function usersCreatedSince(CarbonInterface $since): int;

    public function reviewsTotal(): int;

    /** Reviews with text still waiting for approval. */
    public function reviewsPending(): int;

    public function reviewsCreatedSince(CarbonInterface $since): int;

    /**
     * @param  list<int>  $excludedTmdbIds
     * @return list<array{tmdb_id: int, count: int}> most watched first
     */
    public function mostWatched(array $excludedTmdbIds, int $limit): array;

    /**
     * Highest average approved rating first, star ratings included. Films with fewer than
     * `$minRatings` ratings are left out so a single 5-star rating can't win.
     *
     * @param  list<int>  $excludedTmdbIds
     * @return list<array{tmdb_id: int, count: int, average: float}>
     */
    public function topRated(array $excludedTmdbIds, int $minRatings, int $limit): array;

    /**
     * How much each film was watched plus rated, heaviest first.
     *
     * @param  list<int>  $excludedTmdbIds
     * @return array<int, int> weight by TMDB id
     */
    public function engagementWeights(array $excludedTmdbIds, int $limit): array;

    /**
     * Counts per day since `$since`, keyed by series then by `Y-m-d` date. Days with nothing are absent.
     *
     * @return array{signups: array<string, int>, ratings: array<string, int>, reviews: array<string, int>, lists: array<string, int>, watches: array<string, int>, messages: array<string, int>}
     */
    public function dailyActivity(CarbonInterface $since): array;
}

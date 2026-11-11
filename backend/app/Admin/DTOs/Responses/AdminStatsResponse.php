<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** Everything the admin overview page shows. */
final readonly class AdminStatsResponse implements JsonSerializable
{
    /**
     * @param  array{last_7_days: int, last_30_days: int}  $newUsers
     * @param  array{last_7_days: int, last_30_days: int}  $newReviews
     * @param  array{name: string, days: int, new_users: int, previous_new_users: int, new_reviews: int, previous_new_reviews: int}  $range  the selected window and the equally long one before it
     * @param  list<RankedMovieResponse>  $mostWatched
     * @param  list<RankedMovieResponse>  $topRated
     * @param  list<GenreShareResponse>  $popularGenres
     * @param  list<ActivityPointResponse>  $activity
     */
    public function __construct(
        public int $usersTotal,
        public array $newUsers,
        public int $reviewsTotal,
        public int $reviewsPending,
        public array $newReviews,
        public int $activeToday,
        public int $activeYesterday,
        public int $flaggedReviews,
        public array $range,
        public array $mostWatched,
        public array $topRated,
        public array $popularGenres,
        public array $activity,
    ) {}

    /**
     * Rebuilds the response from its JSON form, which is what the service keeps in the cache.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            usersTotal: $payload['users_total'],
            newUsers: $payload['new_users'],
            reviewsTotal: $payload['reviews_total'],
            reviewsPending: $payload['reviews_pending'],
            newReviews: $payload['new_reviews'],
            activeToday: $payload['active_today'],
            activeYesterday: $payload['active_yesterday'],
            flaggedReviews: $payload['flagged_reviews'],
            range: $payload['range'],
            mostWatched: array_map(fn (array $row): RankedMovieResponse => new RankedMovieResponse($row['tmdb_id'], $row['title'], $row['poster_path'], $row['count'], $row['average']), $payload['most_watched']),
            topRated: array_map(fn (array $row): RankedMovieResponse => new RankedMovieResponse($row['tmdb_id'], $row['title'], $row['poster_path'], $row['count'], $row['average']), $payload['top_rated']),
            popularGenres: array_map(fn (array $row): GenreShareResponse => new GenreShareResponse($row['id'], $row['name'], $row['count'], (float) $row['share']), $payload['popular_genres']),
            activity: array_map(fn (array $row): ActivityPointResponse => new ActivityPointResponse($row['date'], $row['signups'], $row['ratings'], $row['reviews'], $row['lists'], $row['watches'], $row['messages']), $payload['activity']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'users_total' => $this->usersTotal,
            'new_users' => $this->newUsers,
            'reviews_total' => $this->reviewsTotal,
            'reviews_pending' => $this->reviewsPending,
            'new_reviews' => $this->newReviews,
            'active_today' => $this->activeToday,
            'active_yesterday' => $this->activeYesterday,
            'flagged_reviews' => $this->flaggedReviews,
            'range' => $this->range,
            'most_watched' => $this->mostWatched,
            'top_rated' => $this->topRated,
            'popular_genres' => $this->popularGenres,
            'activity' => $this->activity,
        ];
    }
}

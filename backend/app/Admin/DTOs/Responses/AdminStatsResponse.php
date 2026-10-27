<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** Everything the admin overview page shows. */
final readonly class AdminStatsResponse implements JsonSerializable
{
    /**
     * @param  array{last_7_days: int, last_30_days: int}  $newUsers
     * @param  array{last_7_days: int, last_30_days: int}  $newReviews
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
        public array $mostWatched,
        public array $topRated,
        public array $popularGenres,
        public array $activity,
    ) {}

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
            'most_watched' => $this->mostWatched,
            'top_rated' => $this->topRated,
            'popular_genres' => $this->popularGenres,
            'activity' => $this->activity,
        ];
    }
}

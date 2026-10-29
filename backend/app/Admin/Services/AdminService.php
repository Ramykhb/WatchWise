<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\AdminStatsQuery;
use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\DTOs\Requests\ModerateReviewData;
use App\Admin\DTOs\Requests\ReviewModerationQuery;
use App\Admin\DTOs\Requests\UpdateUserData;
use App\Admin\DTOs\Responses\ActivityPointResponse;
use App\Admin\DTOs\Responses\AdminMovieResponse;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminReviewResponse;
use App\Admin\DTOs\Responses\AdminStatsResponse;
use App\Admin\DTOs\Responses\AdminUserResponse;
use App\Admin\DTOs\Responses\GenreShareResponse;
use App\Admin\DTOs\Responses\RankedMovieResponse;
use App\Admin\Exceptions\CannotModerateSelf;
use App\Admin\Exceptions\CannotModerateStaff;
use App\Admin\InterfaceRepository\AdminStatsRepositoryInterface;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use App\Support\MediaType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use JsonSerializable;

class AdminService implements AdminServiceInterface
{
    /** How many rows each "top movies" table shows. */
    private const RANKING_SIZE = 5;

    /** A film needs this many ratings before it can rank as highest rated. */
    private const MIN_RATINGS_TO_RANK = 3;

    private const GENRE_LIMIT = 8;

    /** How many of the most engaged films the genre share is worked out from. */
    private const GENRE_SAMPLE = 1000;

    public function __construct(
        private readonly AdminStatsRepositoryInterface $stats,
        private readonly AdminUserRepositoryInterface $users,
        private readonly AuthServiceInterface $auth,
        private readonly ReviewServiceInterface $reviews,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly GenreServiceInterface $genres,
    ) {}

    public function stats(AdminStatsQuery $query): AdminStatsResponse
    {
        $hidden = $this->catalog->hiddenTmdbIds();
        $lastWeek = now()->subDays(7);
        $lastMonth = now()->subDays(30);
        $windowStart = today()->subDays($query->days - 1);
        $previousStart = $windowStart->copy()->subDays($query->days);
        $tomorrow = today()->addDay();

        $watched = $this->stats->mostWatched($hidden, self::RANKING_SIZE);
        $rated = $this->stats->topRated($hidden, self::MIN_RATINGS_TO_RANK, self::RANKING_SIZE);
        $titles = $this->catalog->summaries(array_values(array_unique([
            ...array_column($watched, 'tmdb_id'),
            ...array_column($rated, 'tmdb_id'),
        ])));

        return new AdminStatsResponse(
            usersTotal: $this->stats->usersTotal(),
            newUsers: [
                'last_7_days' => $this->stats->usersCreatedSince($lastWeek),
                'last_30_days' => $this->stats->usersCreatedSince($lastMonth),
            ],
            reviewsTotal: $this->stats->reviewsTotal(),
            reviewsPending: $this->stats->reviewsPending(),
            newReviews: [
                'last_7_days' => $this->stats->reviewsCreatedSince($lastWeek),
                'last_30_days' => $this->stats->reviewsCreatedSince($lastMonth),
            ],
            activeToday: $this->stats->usersActiveBetween(today(), $tomorrow),
            activeYesterday: $this->stats->usersActiveBetween(today()->subDay(), today()),
            flaggedReviews: $this->reviews->moderationTotals()['flagged'],
            range: [
                'name' => $query->range ?? (string) $query->days,
                'days' => $query->days,
                'new_users' => $this->stats->usersCreatedBetween($windowStart, $tomorrow),
                'previous_new_users' => $this->stats->usersCreatedBetween($previousStart, $windowStart),
                'new_reviews' => $this->stats->reviewsCreatedBetween($windowStart, $tomorrow),
                'previous_new_reviews' => $this->stats->reviewsCreatedBetween($previousStart, $windowStart),
            ],
            mostWatched: array_map(
                fn (array $row): RankedMovieResponse => $this->ranked($row['tmdb_id'], $row['count'], $titles),
                $watched,
            ),
            topRated: array_map(
                fn (array $row): RankedMovieResponse => $this->ranked($row['tmdb_id'], $row['count'], $titles, $row['average']),
                $rated,
            ),
            popularGenres: $this->popularGenres($hidden),
            activity: $this->activity($query->days),
        );
    }

    public function users(AdminUserQuery $query): AdminPageResponse
    {
        return $this->page($this->users->paginate($query), fn (User $user): AdminUserResponse => AdminUserResponse::fromModel($user))
            ->withSummary($this->users->totals());
    }

    public function updateUser(User $admin, User $target, UpdateUserData $data): AdminUserResponse
    {
        if ($admin->role !== 'admin') {
            $this->assertModeratorMayUpdate($admin, $target, $data);
        }

        if ($admin->is($target)) {
            if ($data->role !== null && $data->role !== 'admin') {
                throw CannotModerateSelf::forDemotion();
            }

            if ($data->isActive === false) {
                throw CannotModerateSelf::forDeactivation();
            }
        }

        $updated = $this->users->update($target, $data->changes());

        if ($data->isActive === false) {
            $this->auth->revokeSessions($updated);
        }

        return AdminUserResponse::fromModel($updated);
    }

    /** A moderator may only suspend or reinstate plain members. */
    private function assertModeratorMayUpdate(User $moderator, User $target, UpdateUserData $data): void
    {
        if ($moderator->is($target)) {
            throw CannotModerateSelf::forDeactivation();
        }

        if ($data->role !== null) {
            throw CannotModerateStaff::forRoleChange();
        }

        if ($target->role !== 'user') {
            throw CannotModerateStaff::forStaffAccount();
        }
    }

    public function deleteUser(User $admin, User $target): void
    {
        if ($admin->is($target)) {
            throw CannotModerateSelf::forDeletion();
        }

        $this->auth->revokeSessions($target);
        $this->users->delete($target);
    }

    public function reviews(ReviewModerationQuery $query): AdminPageResponse
    {
        $paginator = $this->reviews->moderationQueue($query->status, $query->page, $query->perPage, $query->search, $query->sentiment, $query->reason);
        $films = $this->filmsOf(collect($paginator->items()));

        return $this->page(
            $paginator,
            fn (Review $review): AdminReviewResponse => AdminReviewResponse::fromModel($review, $this->filmOf($review, $films)),
        )->withSummary($this->reviewSummary());
    }

    public function moderateReview(Review $review, ModerateReviewData $data): AdminReviewResponse
    {
        $moderated = $data->isApproval() ? $this->reviews->approve($review) : $this->reviews->reject($review);

        $moderated->loadMissing(['user', 'reports']);

        return AdminReviewResponse::fromModel($moderated, $this->filmOf($moderated, $this->filmsOf(collect([$moderated]))));
    }

    public function deleteReview(Review $review): void
    {
        $this->reviews->delete($review);
    }

    public function movies(LocalMovieQuery $query): AdminPageResponse
    {
        return $this->page($this->catalog->paginate($query), fn (LocalMovie $movie): AdminMovieResponse => AdminMovieResponse::fromModel($movie))
            ->withSummary(['sync' => $this->catalog->syncSummary()]);
    }

    public function updateMovie(int $tmdbId, UpdateLocalMovieData $data): AdminMovieResponse
    {
        return AdminMovieResponse::fromModel($this->catalog->update($tmdbId, $data));
    }

    /**
     * Catalogue entries for the movies among the reviews, keyed by TMDB id (a TV show can share a movie's id).
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, LocalMovie>
     */
    private function filmsOf(Collection $reviews): Collection
    {
        return $this->catalog->summaries(
            $reviews->where('media_type', MediaType::MOVIE)->pluck('tmdb_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
    }

    /**
     * @param  Collection<int, LocalMovie>  $films
     */
    private function filmOf(Review $review, Collection $films): ?LocalMovie
    {
        return $review->media_type === MediaType::MOVIE ? $films->get((int) $review->tmdb_id) : null;
    }

    /**
     * Whole-site moderation numbers: flagged and removed counts and the sentiment split of the reviews that stand.
     *
     * @return array{flagged: int, removed: int, sentiment_total: int, sentiment: array<string, array{count: int, percent: float}>}
     */
    private function reviewSummary(): array
    {
        $totals = $this->reviews->moderationTotals();
        $whole = array_sum($totals['sentiment']);

        return [
            'flagged' => $totals['flagged'],
            'removed' => $totals['removed'],
            'sentiment_total' => $whole,
            'sentiment' => array_map(
                fn (int $count): array => ['count' => $count, 'percent' => $whole > 0 ? round($count / $whole * 100, 1) : 0.0],
                $totals['sentiment'],
            ),
        ];
    }

    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): JsonSerializable  $toResponse
     */
    private function page(LengthAwarePaginator $paginator, callable $toResponse): AdminPageResponse
    {
        return new AdminPageResponse(
            array_map($toResponse, $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    /**
     * @param  Collection<int, LocalMovie>  $titles
     */
    private function ranked(int $tmdbId, int $count, Collection $titles, ?float $average = null): RankedMovieResponse
    {
        $movie = $titles->get($tmdbId);

        return new RankedMovieResponse($tmdbId, $movie?->title, $movie?->poster_path, $count, $average);
    }

    /**
     * @param  list<int>  $hidden
     * @return list<GenreShareResponse>
     */
    private function popularGenres(array $hidden): array
    {
        $weights = $this->stats->engagementWeights($hidden, self::GENRE_SAMPLE);
        $counts = [];

        foreach ($this->catalog->summaries(array_keys($weights)) as $tmdbId => $movie) {
            foreach ($movie->genre_ids ?? [] as $genreId) {
                $counts[(int) $genreId] = ($counts[(int) $genreId] ?? 0) + $weights[$tmdbId];
            }
        }

        if ($counts === []) {
            return [];
        }

        arsort($counts);
        $names = $this->genreNames();
        $total = array_sum($counts);
        $shares = [];

        foreach (array_slice($counts, 0, self::GENRE_LIMIT, true) as $genreId => $count) {
            $shares[] = new GenreShareResponse($genreId, $names[$genreId] ?? "Genre {$genreId}", $count, round($count / $total * 100, 1));
        }

        return $shares;
    }

    /**
     * @return array<int, string>
     */
    private function genreNames(): array
    {
        try {
            $all = $this->genres->all();
        } catch (TmdbUnavailable) {
            return [];
        }

        return collect($all)->mapWithKeys(fn (GenreResponse $genre): array => [$genre->id => $genre->name])->all();
    }

    /**
     * One point per day, today last, with zeros where nothing happened.
     *
     * @return list<ActivityPointResponse>
     */
    private function activity(int $days): array
    {
        $first = today()->subDays($days - 1);
        $series = $this->stats->dailyActivity($first);
        $points = [];

        for ($day = $first->copy(); $day->lte(today()); $day->addDay()) {
            $date = $day->toDateString();

            $points[] = new ActivityPointResponse(
                $date,
                $series['signups'][$date] ?? 0,
                $series['ratings'][$date] ?? 0,
                $series['reviews'][$date] ?? 0,
                $series['lists'][$date] ?? 0,
                $series['watches'][$date] ?? 0,
                $series['messages'][$date] ?? 0,
            );
        }

        return $points;
    }
}

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
        return $this->page($this->users->paginate($query), fn (User $user): AdminUserResponse => AdminUserResponse::fromModel($user));
    }

    public function updateUser(User $admin, User $target, UpdateUserData $data): AdminUserResponse
    {
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
        return $this->page(
            $this->reviews->moderationQueue($query->status, $query->page, $query->perPage, $query->search, $query->sentiment, $query->reason),
            fn (Review $review): AdminReviewResponse => AdminReviewResponse::fromModel($review),
        );
    }

    public function moderateReview(Review $review, ModerateReviewData $data): AdminReviewResponse
    {
        $moderated = $data->isApproval() ? $this->reviews->approve($review) : $this->reviews->reject($review);

        return AdminReviewResponse::fromModel($moderated->loadMissing('user'));
    }

    public function deleteReview(Review $review): void
    {
        $this->reviews->delete($review);
    }

    public function movies(LocalMovieQuery $query): AdminPageResponse
    {
        return $this->page($this->catalog->paginate($query), fn (LocalMovie $movie): AdminMovieResponse => AdminMovieResponse::fromModel($movie));
    }

    public function updateMovie(int $tmdbId, UpdateLocalMovieData $data): AdminMovieResponse
    {
        return AdminMovieResponse::fromModel($this->catalog->update($tmdbId, $data));
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

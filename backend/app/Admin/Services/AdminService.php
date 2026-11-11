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
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\Mail\AccountReinstatedMail;
use App\Admin\Mail\AccountSuspendedMail;
use App\Admin\Support\AdminAction;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use App\Support\MediaType;
use App\Support\SendsMailAfterResponse;
use App\User\InterfaceServices\AccountServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonSerializable;

class AdminService implements AdminServiceInterface
{
    use SendsMailAfterResponse;

    /** How many rows each "top movies" table shows. */
    private const RANKING_SIZE = 5;

    /** A film needs this many ratings before it can rank as highest rated. */
    private const MIN_RATINGS_TO_RANK = 3;

    private const GENRE_LIMIT = 8;

    /** Seconds a built stats response is reused before it is worked out again. */
    private const STATS_CACHE_TTL = 60;

    /** Cache key of one stats response: version, day, number of days and named range of the window. */
    private const STATS_CACHE_KEY = 'admin:stats:%s:%s:%d:%s';

    /** Cache key of the token that is part of every stats key; replacing it retires all cached windows at once. */
    private const STATS_VERSION_KEY = 'admin:stats:version';

    /** How many of the most engaged films the genre share is worked out from. */
    private const GENRE_SAMPLE = 1000;

    /** Movie fields whose text is too long for the audit log: it records that they changed, not the text. */
    private const UNLOGGED_MOVIE_FIELDS = ['overview'];

    public function __construct(
        private readonly AdminStatsRepositoryInterface $stats,
        private readonly AdminUserRepositoryInterface $users,
        private readonly AuthServiceInterface $auth,
        private readonly ReviewServiceInterface $reviews,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly GenreServiceInterface $genres,
        private readonly AccountServiceInterface $accounts,
        private readonly AdminAuditServiceInterface $audit,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function stats(AdminStatsQuery $query): AdminStatsResponse
    {
        $key = sprintf(
            self::STATS_CACHE_KEY,
            Cache::get(self::STATS_VERSION_KEY, 'initial'),
            today()->toDateString(),
            $query->days,
            $query->range ?? 'none',
        );

        $payload = Cache::remember(
            $key,
            self::STATS_CACHE_TTL,
            fn (): array => json_decode(json_encode($this->buildStats($query), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
        );

        return AdminStatsResponse::fromPayload($payload);
    }

    /**
     * Retires every cached stats window, so the next request counts afresh.
     */
    private function forgetStats(): void
    {
        Cache::forever(self::STATS_VERSION_KEY, (string) Str::uuid());
    }

    private function buildStats(AdminStatsQuery $query): AdminStatsResponse
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

        [$updated, $wasActive] = DB::transaction(function () use ($admin, $target, $data): array {
            $current = $this->users->lockForUpdate($target);
            $wasRole = $current->role;
            $wasActive = (bool) $current->is_active;

            $updated = $this->users->update($current, $data->changes());
            $this->auditUserUpdate($admin, $updated, $wasRole, $wasActive);

            return [$updated, $wasActive];
        });

        $this->tellMemberOfActiveChange($updated, $wasActive);

        if ($data->isActive === false) {
            $this->auth->revokeSessions($updated);
        }

        return AdminUserResponse::fromModel($updated);
    }

    /**
     * Emails the member's stored address, verified or not, once their active state really changed: a suspended member
     * can't sign in, so the email is the only way they hear of it. It names neither the staff member nor a reason.
     * Called after the transaction committed; sending is deferred and a failure is only reported.
     */
    private function tellMemberOfActiveChange(User $user, bool $wasActive): void
    {
        if ((bool) $user->is_active === $wasActive) {
            return;
        }

        $this->mailLater($user->email, $user->is_active ? new AccountReinstatedMail($user->name) : new AccountSuspendedMail($user->name));
    }

    /** One entry for each of the role and the active state that really changed; sending the value a member already has records nothing. */
    private function auditUserUpdate(User $admin, User $user, string $wasRole, bool $wasActive): void
    {
        $label = $this->userLabel($user);

        if ($user->role !== $wasRole) {
            $this->audit->record($admin, AdminAction::UserRoleChanged, $user->id, $label, ['role' => ['from' => $wasRole, 'to' => $user->role]]);
        }

        if ((bool) $user->is_active !== $wasActive) {
            $action = $user->is_active ? AdminAction::UserReinstated : AdminAction::UserSuspended;
            $this->audit->record($admin, $action, $user->id, $label, ['is_active' => ['from' => $wasActive, 'to' => (bool) $user->is_active]]);
        }
    }

    /** A moderator may only suspend or reinstate plain members; sending the role the member already has changes nothing, so it is allowed. */
    private function assertModeratorMayUpdate(User $moderator, User $target, UpdateUserData $data): void
    {
        if ($moderator->is($target)) {
            throw CannotModerateSelf::forDeactivation();
        }

        if ($data->role !== null && $data->role !== $target->role) {
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

        $id = $target->id;
        $label = $this->userLabel($target);

        $this->auth->revokeSessions($target);

        DB::transaction(function () use ($admin, $target, $id, $label): void {
            $this->audit->record($admin, AdminAction::UserDeleted, $id, $label);
            $this->accounts->deleteAccount($target);
        });

        $this->forgetStats();
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

    public function moderateReview(User $moderator, Review $review, ModerateReviewData $data): AdminReviewResponse
    {
        [$moderated, $removedText] = DB::transaction(function () use ($moderator, $review, $data): array {
            $current = $this->reviews->lockForUpdate($review)->loadMissing('user');
            $was = $this->reviewState($current);
            $removedText = ! $data->isApproval() && filled($current->content);
            $reportsResolved = $this->reviews->openReportsCount($current);

            $moderated = $data->isApproval() ? $this->reviews->approve($current) : $this->reviews->reject($current);
            $now = $this->reviewState($moderated);

            if ($now !== $was || $reportsResolved > 0) {
                $this->audit->record(
                    $moderator,
                    $data->isApproval() ? AdminAction::ReviewApproved : AdminAction::ReviewRejected,
                    $moderated->id,
                    $this->reviewLabel($moderated),
                    ['status' => ['from' => $was, 'to' => $now], 'reports_resolved' => $reportsResolved],
                );
            }

            return [$moderated, $removedText];
        });
        $this->forgetStats();

        if ($removedText) {
            $this->tellAuthorReviewRemoved($moderated);
        }

        $moderated->loadMissing(['user', 'reports']);

        return AdminReviewResponse::fromModel($moderated, $this->filmOf($moderated, $this->filmsOf(collect([$moderated]))));
    }

    public function deleteReview(User $moderator, Review $review): void
    {
        $review->loadMissing('user');
        $id = $review->id;
        $label = $this->reviewLabel($review);
        $hadText = filled($review->content);

        DB::transaction(function () use ($moderator, $review, $id, $label): void {
            $this->reviews->delete($review);
            $this->audit->record($moderator, AdminAction::ReviewDeleted, $id, $label);
        });
        $this->forgetStats();

        if ($hadText) {
            $this->tellAuthorReviewRemoved($review);
        }
    }

    /**
     * Tells the author their review was removed. Called after the transaction committed; a failure to notify is
     * reported and never undoes or fails the moderation.
     */
    private function tellAuthorReviewRemoved(Review $review): void
    {
        rescue(fn () => $this->notifications->reviewRemoved($review));
    }

    public function movies(LocalMovieQuery $query): AdminPageResponse
    {
        return $this->page($this->catalog->paginate($query), fn (LocalMovie $movie): AdminMovieResponse => AdminMovieResponse::fromModel($movie))
            ->withSummary(['sync' => $this->catalog->syncSummary()]);
    }

    public function updateMovie(User $admin, int $tmdbId, UpdateLocalMovieData $data): AdminMovieResponse
    {
        $movie = DB::transaction(function () use ($admin, $tmdbId, $data): LocalMovie {
            $movie = $this->catalog->update($tmdbId, $data);
            $changes = $this->movieChanges($movie, $data);

            if ($changes !== []) {
                $this->audit->record($admin, AdminAction::MovieUpdated, $tmdbId, $movie->title, $changes);
            }

            return $movie;
        });
        $this->forgetStats();

        return AdminMovieResponse::fromModel($movie);
    }

    /**
     * The submitted fields the update really changed, as old and new value; text too long to keep is only marked changed.
     * Read from the saved model, so the old values are the ones the update itself replaced.
     *
     * @return array<string, array<string, mixed>>
     */
    private function movieChanges(LocalMovie $movie, UpdateLocalMovieData $data): array
    {
        $before = (new LocalMovie)->setRawAttributes($movie->getPrevious());
        $changes = [];

        foreach (array_keys($data->changes()) as $field) {
            if ($movie->wasChanged($field)) {
                $changes[$field] = in_array($field, self::UNLOGGED_MOVIE_FIELDS, true)
                    ? ['changed' => true]
                    : ['from' => $this->auditValue($before->{$field}), 'to' => $this->auditValue($movie->{$field})];
            }
        }

        return $changes;
    }

    private function auditValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : $value;
    }

    /** Usernames are what the log keeps as an account's label; emails never are. */
    private function userLabel(User $user): string
    {
        return $user->username ?? $user->name;
    }

    private function reviewLabel(Review $review): ?string
    {
        return $review->user === null ? null : 'Review by '.$this->userLabel($review->user);
    }

    /** Where a review stands in moderation, ignoring open reports (those only make it show as flagged). */
    private function reviewState(Review $review): string
    {
        return match (true) {
            $review->removed_at !== null => 'removed',
            (bool) $review->is_approved => 'approved',
            default => 'pending',
        };
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

<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminRatingBurstResponse;
use App\Admin\DTOs\Responses\AdminRatingFilmResponse;
use App\Admin\DTOs\Responses\AdminRatingResponse;
use App\Admin\Exceptions\CannotModerateSelf;
use App\Admin\Exceptions\CannotModerateStaff;
use App\Admin\Exceptions\RatingHasReviewText;
use App\Admin\Exceptions\RatingIsModerated;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminRatingServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Rating\DTOs\Requests\RatingBurstQuery;
use App\Rating\DTOs\Requests\RatingQuery;
use App\Rating\InterfaceServices\RatingServiceInterface;
use App\Support\MediaType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminRatingService implements AdminRatingServiceInterface
{
    public function __construct(
        private readonly RatingServiceInterface $ratings,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly AdminAuditServiceInterface $audit,
        private readonly AdminServiceInterface $admin,
    ) {}

    public function ratings(RatingQuery $query): AdminPageResponse
    {
        $paginator = $this->ratings->page($query);
        $films = $this->filmsOf($paginator->items());

        return new AdminPageResponse(
            array_map(
                fn (Review $rating): AdminRatingResponse => AdminRatingResponse::fromModel($rating, $rating->media_type === MediaType::MOVIE ? $films->get((int) $rating->tmdb_id) : null),
                $paginator->items(),
            ),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
            $this->ratings->summary($query),
        );
    }

    public function film(int $tmdbId, string $mediaType): AdminRatingFilmResponse
    {
        $picture = $this->ratings->filmPicture($tmdbId, $mediaType);

        return new AdminRatingFilmResponse(
            $tmdbId,
            $mediaType,
            $this->catalogEntry($mediaType, $tmdbId),
            $picture['total'],
            $picture['average'],
            $picture['distribution'],
            $picture['with_text_count'],
            $picture['first_rated_at'],
            $picture['last_rated_at'],
        );
    }

    public function bursts(RatingBurstQuery $query): AdminPageResponse
    {
        $end = now();
        $start = $end->copy()->subHours($query->hours);
        $paginator = $this->ratings->bursts($query, $end);

        return new AdminPageResponse(
            array_map(fn (Review $row): AdminRatingBurstResponse => AdminRatingBurstResponse::fromModel($row, $start, $end), $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    public function delete(User $staff, Review $rating): void
    {
        $rating->loadMissing('user');
        $label = $this->ratingLabel($rating);

        DB::transaction(function () use ($staff, $rating, $label): void {
            $current = $this->ratings->lockForUpdate($rating);

            if (! $current->isStarOnly()) {
                throw new RatingHasReviewText;
            }

            if ($current->removed_at !== null) {
                throw new RatingIsModerated;
            }

            $reportsDeleted = $this->ratings->remove($current);
            $this->audit->record(
                $staff,
                AdminAction::RatingDeleted,
                $current->id,
                $label,
                ['rating' => ['from' => (int) $current->rating, 'to' => null], 'reports_deleted' => $reportsDeleted],
            );
        });
        $this->admin->forgetStats();
    }

    public function purge(User $staff, User $member): int
    {
        if ($staff->is($member)) {
            throw CannotModerateSelf::forRatingsPurge();
        }

        if ($staff->role !== 'admin' && $member->role !== 'user') {
            throw CannotModerateStaff::forStaffAccount();
        }

        $removed = DB::transaction(function () use ($staff, $member): int {
            $deleted = $this->ratings->removeAllOf($member);

            if ($deleted['ratings'] > 0) {
                $this->audit->record(
                    $staff,
                    AdminAction::RatingsPurged,
                    $member->id,
                    'Ratings by '.$this->userLabel($member),
                    ['removed' => $deleted['ratings'], 'reports_deleted' => $deleted['reports']],
                );
            }

            return $deleted['ratings'];
        });

        if ($removed > 0) {
            $this->admin->forgetStats();
        }

        return $removed;
    }

    /** "Rating by maya on Fight Club"; a film that is not in the catalogue (or any TV show) shows its id, like "550" or "tv-1399". */
    private function ratingLabel(Review $rating): string
    {
        $title = $this->catalogEntry($rating->media_type, (int) $rating->tmdb_id)?->title;

        return 'Rating by '.$this->userLabel($rating->user).' on '.($title ?? MediaType::key($rating->media_type, (int) $rating->tmdb_id));
    }

    /** Usernames are what the log keeps as an account's label; emails never are. */
    private function userLabel(User $user): string
    {
        return $user->username ?? $user->name;
    }

    /** The local catalogue's copy of the title, or null for a TV show (the catalogue only holds movies) and for films it never stored. */
    private function catalogEntry(string $mediaType, int $tmdbId): ?LocalMovie
    {
        return $mediaType === MediaType::MOVIE ? $this->catalog->summaries([$tmdbId])->get($tmdbId) : null;
    }

    /**
     * Catalogue entries for the movies among the ratings, keyed by TMDB id, in one lookup.
     *
     * @param  list<Review>  $ratings
     * @return Collection<int, LocalMovie>
     */
    private function filmsOf(array $ratings): Collection
    {
        return $this->catalog->summaries(
            collect($ratings)
                ->where('media_type', MediaType::MOVIE)
                ->map(fn (Review $rating): int => (int) $rating->tmdb_id)
                ->unique()
                ->values()
                ->all(),
        );
    }
}

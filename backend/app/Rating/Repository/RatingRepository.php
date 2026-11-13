<?php

namespace App\Rating\Repository;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Rating\DTOs\Requests\RatingQuery;
use App\Rating\InterfaceRepository\RatingRepositoryInterface;
use App\Report\Support\ReportTargetType;
use App\Support\LikePattern;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RatingRepository implements RatingRepositoryInterface
{
    /** Rows deleted per statement, so a member with thousands of ratings stays under the databases' parameter limits. */
    private const DELETE_CHUNK = 500;

    public function find(User $user, int $tmdbId, string $mediaType): ?int
    {
        $rating = $user->reviews()->where('tmdb_id', $tmdbId)->where('media_type', $mediaType)->value('rating');

        return $rating === null ? null : (int) $rating;
    }

    public function save(User $user, int $tmdbId, string $mediaType, int $rating): void
    {
        // Upsert on (user_id, tmdb_id, media_type), so two quick clicks can't create two rows. A rating with no
        // text has nothing to moderate, so a new row is approved; an existing review keeps its state.
        Review::upsert(
            [[
                'user_id' => $user->id,
                'tmdb_id' => $tmdbId,
                'media_type' => $mediaType,
                'rating' => $rating,
                'content' => null,
                'sentiment' => null,
                'is_approved' => true,
            ]],
            ['user_id', 'tmdb_id', 'media_type'],
            ['rating', 'updated_at'],
        );
    }

    public function paginate(RatingQuery $query): LengthAwarePaginator
    {
        return $this->matching($query)
            ->with('user:id,name,username')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function starCounts(RatingQuery $query): array
    {
        return $this->matching($query)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->toBase()
            ->pluck('total', 'rating')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    public function withTextCount(int $tmdbId, string $mediaType): int
    {
        return Review::query()->withText()->where('tmdb_id', $tmdbId)->where('media_type', $mediaType)->count();
    }

    public function ratedRange(int $tmdbId, string $mediaType): array
    {
        $range = Review::query()->starOnly()
            ->where('tmdb_id', $tmdbId)
            ->where('media_type', $mediaType)
            ->toBase()
            ->selectRaw('min(created_at) as first_at, max(created_at) as last_at')
            ->first();

        return [
            'first' => $range->first_at === null ? null : Carbon::parse($range->first_at),
            'last' => $range->last_at === null ? null : Carbon::parse($range->last_at),
        ];
    }

    public function paginateBursts(CarbonInterface $since, CarbonInterface $until, int $min, int $page, int $perPage): LengthAwarePaginator
    {
        return Review::query()->starOnly()
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', $until)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [$min])
            ->with('user:id,name,username,created_at')
            ->orderByDesc('total')
            ->orderBy('user_id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function countCreated(CarbonInterface $from, ?CarbonInterface $until): int
    {
        return Review::query()->starOnly()
            ->where('created_at', '>=', $from)
            ->when($until !== null, fn (Builder $ratings) => $ratings->where('created_at', '<', $until))
            ->count();
    }

    public function lockForUpdate(Review $rating): Review
    {
        return Review::query()->whereKey($rating->getKey())->lockForUpdate()->firstOrFail();
    }

    public function delete(Review $rating): int
    {
        $reports = $rating->reports()->delete();
        $rating->delete();

        return $reports;
    }

    public function deleteAllOf(User $member): array
    {
        $ids = Review::query()->starOnly()->whereNull('removed_at')->where('user_id', $member->id)->lockForUpdate()->pluck('id')->all();
        $reports = 0;

        foreach (array_chunk($ids, self::DELETE_CHUNK) as $chunk) {
            $reports += Report::query()->where('target_type', ReportTargetType::Review)->whereIn('target_id', $chunk)->delete();
            Review::query()->whereIn('id', $chunk)->delete();
        }

        return ['ratings' => count($ids), 'reports' => $reports];
    }

    /**
     * Star-only ratings that match every filter that is set.
     *
     * @return Builder<Review>
     */
    private function matching(RatingQuery $query): Builder
    {
        return Review::query()->starOnly()
            ->when($query->tmdbId !== null, fn (Builder $ratings) => $ratings->where('tmdb_id', $query->tmdbId))
            ->when($query->mediaType !== null, fn (Builder $ratings) => $ratings->where('media_type', $query->mediaType))
            ->when($query->userId !== null, fn (Builder $ratings) => $ratings->where('user_id', $query->userId))
            ->when($query->rating !== null, fn (Builder $ratings) => $ratings->where('rating', $query->rating))
            ->when($query->search !== '', function (Builder $ratings) use ($query) {
                $pattern = LikePattern::contains(mb_strtolower($query->search));

                $ratings->whereIn('user_id', User::query()->select('id')->where(fn (Builder $member) => $member
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(username) LIKE ? ESCAPE '!'", [$pattern])));
            });
    }
}

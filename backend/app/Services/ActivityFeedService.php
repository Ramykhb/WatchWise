<?php

namespace App\Services;

use App\Http\Resources\MemberResource;
use App\Http\Resources\MovieListResource;
use App\Http\Resources\ReviewResource;
use App\Models\ActivityFeed;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Writes rows to `activity_feed` and turns them into the cards Profile and Home show.
 * Each row points at the record it is about (a review or a list) through reference_type/id.
 */
class ActivityFeedService
{
    public function record(User $user, string $type, Model $reference, ?int $tmdbId = null, bool $isPublic = true): void
    {
        ActivityFeed::create([
            'user_id' => $user->id,
            'type' => $type,
            'tmdb_id' => $tmdbId,
            'reference_id' => $reference->getKey(),
            'reference_type' => $reference->getMorphClass(),
            'is_public' => $isPublic,
        ]);
    }

    /**
     * Public activity of the given users, newest first, in the shape the frontend renders.
     *
     * @param  array<int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    public function forUsers(array $userIds, int $limit = 20, bool $includePrivate = false): Collection
    {
        $rows = ActivityFeed::with('user')
            ->whereIn('user_id', $userIds)
            ->when(! $includePrivate, fn ($q) => $q->where('is_public', true))
            ->latest('created_at')->latest('id')
            ->limit($limit)
            ->get();

        $reviews = Review::with('user')->whereIn('id', $this->idsOf($rows, Review::class))->get()->keyBy('id');
        $lists = MovieList::with(['user', 'items'])->withCount('items')
            ->whereIn('id', $this->idsOf($rows, MovieList::class))->get()->keyBy('id');

        return $rows->map(function (ActivityFeed $row) use ($reviews, $lists) {
            $base = [
                'id' => $row->id,
                'user' => new MemberResource($row->user),
                'tmdb_id' => $row->tmdb_id,
                'created_at' => $row->created_at?->toJSON(),
            ];

            return match ($row->type) {
                'review', 'rating' => ($review = $reviews->get($row->reference_id)) ? [
                    ...$base,
                    'kind' => $row->type === 'review' ? 'reviewed' : 'rated',
                    'rating' => $review->rating,
                    'review' => new ReviewResource($review),
                ] : null,
                'list_created', 'list_movie_added' => ($list = $lists->get($row->reference_id)) && $list->is_public ? [
                    ...$base,
                    'kind' => 'list',
                    'list' => new MovieListResource($list),
                ] : null,
                'watchlist_add' => [...$base, 'kind' => 'watchlist'],
                default => null,
            };
        })->filter()->values();
    }

    /**
     * @param  Collection<int, ActivityFeed>  $rows
     * @return array<int>
     */
    private function idsOf(Collection $rows, string $class): array
    {
        $morph = (new $class)->getMorphClass();

        return $rows->where('reference_type', $morph)->pluck('reference_id')->unique()->all();
    }
}

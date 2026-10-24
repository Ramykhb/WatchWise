<?php

namespace App\Services;

use App\Auth\CurrentUser\CurrentUser;
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
    public function __construct(private readonly ProfilePrivacy $privacy) {}

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
     * Activity of the given users that the viewer may see, newest first, in the shape the
     * frontend renders. Privacy is checked against each owner's settings *now* (profile
     * visibility and section switches), not the flag stored when the activity happened, and
     * reviews waiting for moderation only reach their author and admins.
     *
     * @param  array<int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    public function forUsers(array $userIds, CurrentUser $viewer, int $limit = 20): Collection
    {
        // Read ahead, since some rows are filtered out below.
        $rows = ActivityFeed::with('user.preferences')
            ->whereIn('user_id', $userIds)
            ->latest('created_at')->latest('id')
            ->limit($limit * 4)
            ->get();

        $reviews = Review::with('user')->whereIn('id', $this->idsOf($rows, Review::class))->get()->keyBy('id');
        $lists = MovieList::with(['user', 'items'])->withCount('items')
            ->whereIn('id', $this->idsOf($rows, MovieList::class))->get()->keyBy('id');

        // Each owner's answers are worked out once per call (visibility can need a query).
        $answers = [];
        $showsFor = function (User $owner, string $section) use (&$answers, $viewer) {
            return $answers[$owner->id][$section] ??= $this->privacy->shows($owner, $viewer, $section);
        };

        return $rows->map(function (ActivityFeed $row) use ($reviews, $lists, $viewer, $showsFor) {
            $owner = $row->user;
            $shows = fn (string $section) => $showsFor($owner, $section);
            $base = [
                'id' => $row->id,
                'user' => new MemberResource($owner),
                'tmdb_id' => $row->tmdb_id,
                'created_at' => $row->created_at?->toJSON(),
            ];

            switch ($row->type) {
                case 'review':
                case 'rating':
                    $review = $reviews->get($row->reference_id);
                    $kind = $row->type === 'review' ? 'reviewed' : 'rated';
                    $visible = $review !== null
                        && $shows($kind === 'reviewed' ? 'reviews' : 'ratings')
                        && ($review->is_approved || $this->privacy->seesUnapprovedReviews($owner, $viewer));

                    return $visible ? [...$base, 'kind' => $kind, 'rating' => $review->rating, 'review' => new ReviewResource($review)] : null;
                case 'list_created':
                case 'list_movie_added':
                    $list = $lists->get($row->reference_id);
                    $visible = $list !== null && $shows('lists') && ($list->is_public || $viewer->id() === $owner->id);

                    return $visible ? [...$base, 'kind' => 'list', 'list' => new MovieListResource($list)] : null;
                case 'watchlist_add':
                    return $shows('watchlist') ? [...$base, 'kind' => 'watchlist'] : null;
                default:
                    return null;
            }
        })->filter()->take($limit)->values();
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

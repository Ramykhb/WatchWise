<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\Models\LocalMovie;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Support\MediaType;
use App\User\DTOs\Responses\HomeResponse;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\Resources\MemberResource;

class HomeService implements HomeServiceInterface
{
    /** How many films the Home page's Featured row holds. */
    private const FEATURED_LIMIT = 10;

    public function __construct(
        private readonly HomeRepositoryInterface $home,
        private readonly MovieCatalogServiceInterface $catalog,
    ) {}

    public function home(CurrentUser $viewer): HomeResponse
    {
        $user = $viewer->user();
        $rooms = $this->home->busiestRooms(3, $user->id)
            ->map(fn (DiscussionRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'members' => $room->members_count,
            ])->values()->all();

        $night = $this->home->nextNightFor($user);
        $leader = $night?->votes->groupBy('tmdb_id')->sortByDesc(fn ($votes) => $votes->count())->first();

        return new HomeResponse(
            $rooms,
            $night ? [
                'id' => $night->id,
                'title' => $night->title,
                'scheduled_at' => $night->scheduled_at->toJSON(),
                'guests' => MemberResource::collection($night->invites->where('status', 'accepted')->pluck('user')->filter()->values()),
                'leader' => $leader ? ['tmdb_id' => $leader->first()->tmdb_id, 'votes' => $leader->count()] : null,
            ] : null,
            $this->activity($user),
            $this->featured(),
        );
    }

    /**
     * The films an admin featured, shaped like the movie cards of the TMDB lists. The catalogue does not keep
     * votes or a backdrop, so those come back empty.
     *
     * @return list<array<string, mixed>>
     */
    private function featured(): array
    {
        return $this->catalog->featured(self::FEATURED_LIMIT)->map(fn (LocalMovie $movie): array => [
            'id' => $movie->tmdb_id,
            'media_type' => MediaType::MOVIE,
            'title' => $movie->title,
            'overview' => $movie->overview ?? '',
            'release_date' => $movie->release_date?->toDateString(),
            'year' => $movie->release_date?->year,
            'rating' => 0.0,
            'vote_count' => 0,
            'genre_ids' => $movie->genre_ids ?? [],
            'poster_url' => $movie->poster_path ? config('services.tmdb.image_url').'/w500'.$movie->poster_path : null,
            'backdrop_url' => null,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function activity(User $viewer): array
    {
        $recent = $this->home->recentActivity($viewer, 8, $this->catalog->hiddenMovies());
        $reviews = $recent['reviews']->map(function (Review $review): array {
            $hasText = $review->content !== null && $review->content !== '';

            return [
                'id' => 'review:'.$review->id,
                'kind' => $hasText ? 'reviewed' : 'rated',
                'user' => (new MemberResource($review->user))->resolve(),
                'tmdb_id' => $review->tmdb_id,
                'media_type' => $review->media_type ?? MediaType::MOVIE,
                'rating' => $review->user->shares('ratings') ? $review->rating : null,
                'excerpt' => $hasText && ! $review->has_spoilers ? mb_substr($review->content, 0, 120) : null,
                'has_spoilers' => $hasText && $review->has_spoilers,
                'created_at' => $review->updated_at->toJSON(),
            ];
        });
        $lists = $recent['lists']->map(function (MovieList $list): array {
            $cover = $list->items->first();

            return [
                'id' => 'list:'.$list->id,
                'kind' => 'list',
                'user' => (new MemberResource($list->user))->resolve(),
                'tmdb_id' => $cover?->tmdb_id,
                'media_type' => $cover?->media_type ?? MediaType::MOVIE,
                'list' => ['id' => $list->id, 'title' => $list->title],
                'created_at' => $list->updated_at->toJSON(),
            ];
        });
        $watchlist = $recent['watchlist']->map(fn (Watchlist $item): array => [
            'id' => 'watchlist:'.$item->id,
            'kind' => $item->status === 'watched' ? 'watched' : 'watchlist',
            'user' => (new MemberResource($item->user))->resolve(),
            'tmdb_id' => $item->tmdb_id,
            'media_type' => $item->media_type ?? MediaType::MOVIE,
            'created_at' => ($item->status === 'watched' ? ($item->watched_at ?? $item->created_at) : $item->created_at)->toJSON(),
        ]);

        return $reviews->concat($lists)->concat($watchlist)
            ->sortBy([['created_at', 'desc'], ['id', 'desc']])->take(8)->values()->all();
    }
}

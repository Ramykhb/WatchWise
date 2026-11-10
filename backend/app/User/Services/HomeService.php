<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Support\MediaType;
use App\User\DTOs\Responses\HomeResponse;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\Resources\MemberResource;

class HomeService implements HomeServiceInterface
{
    public function __construct(private readonly HomeRepositoryInterface $home) {}

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
        );
    }

    /** @return list<array<string, mixed>> */
    private function activity(User $viewer): array
    {
        $recent = $this->home->recentActivity($viewer, 8);
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

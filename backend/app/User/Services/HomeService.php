<?php

namespace App\User\Services;

use App\ActivityFeed\Services\ActivityFeedService;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\User\DTOs\Responses\HomeResponse;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use App\User\InterfaceRepository\ProfileRepositoryInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\Resources\MemberResource;

class HomeService implements HomeServiceInterface
{
    public function __construct(
        private readonly HomeRepositoryInterface $home,
        private readonly ProfileRepositoryInterface $profiles,
        private readonly ActivityFeedService $activity,
    ) {}

    public function home(CurrentUser $viewer): HomeResponse
    {
        $user = $viewer->user();
        $followingIds = $this->profiles->followingIds($user)->all();

        $rooms = $this->home->busiestRooms(3)
            ->map(fn (DiscussionRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'members' => $this->home->roomMemberCount($room),
            ])->values()->all();

        $night = $this->home->nextNightFor($user);
        $leader = $night?->votes->groupBy('tmdb_id')->sortByDesc(fn ($votes) => $votes->count())->first();

        return new HomeResponse(
            $this->activity->forUsers($followingIds, $viewer, 8),
            $rooms,
            $night ? [
                'id' => $night->id,
                'title' => $night->title,
                'scheduled_at' => $night->scheduled_at->toJSON(),
                'guests' => MemberResource::collection($night->invites->where('status', 'accepted')->pluck('user')->filter()->values()),
                'leader' => $leader ? ['tmdb_id' => $leader->first()->tmdb_id, 'votes' => $leader->count()] : null,
            ] : null,
        );
    }
}

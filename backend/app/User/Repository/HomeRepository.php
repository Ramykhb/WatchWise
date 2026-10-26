<?php

namespace App\User\Repository;

use App\Models\DiscussionRoom;
use App\Models\MovieNight;
use App\Models\User;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class HomeRepository implements HomeRepositoryInterface
{
    public function busiestRooms(int $limit): Collection
    {
        return DiscussionRoom::withCount('messages')
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->limit($limit)
            ->get();
    }

    public function roomMemberCount(DiscussionRoom $room): int
    {
        return $room->members()->count();
    }

    public function nextNightFor(User $user): ?MovieNight
    {
        return MovieNight::with(['invites.user', 'votes'])
            ->where('scheduled_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->where(fn ($query) => $query->where('organizer_id', $user->id)->orWhereHas('invites', fn ($invites) => $invites->where('user_id', $user->id)->where('status', '!=', 'declined')))
            ->orderBy('scheduled_at')
            ->first();
    }
}

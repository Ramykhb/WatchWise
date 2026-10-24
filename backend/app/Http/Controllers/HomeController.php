<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\MemberResource;
use App\Models\DiscussionRoom;
use App\Models\MovieNight;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;

/** GET /api/home — the signed-in Home page: friends' activity, busy rooms and your next movie night. */
class HomeController extends Controller
{
    public function __invoke(CurrentUser $current, ActivityFeedService $activity): JsonResponse
    {
        $user = $current->user();
        $followingIds = $user->following()->pluck('users.id')->all();

        $rooms = DiscussionRoom::withCount('messages')
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->limit(3)
            ->get()
            ->map(fn (DiscussionRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'members' => $room->messages()->distinct()->count('user_id'),
            ]);

        $night = MovieNight::with(['invites.user', 'votes'])
            ->where('scheduled_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('organizer_id', $user->id)->orWhereHas('invites', fn ($i) => $i->where('user_id', $user->id)->where('status', '!=', 'declined')))
            ->orderBy('scheduled_at')
            ->first();

        $leader = $night?->votes->groupBy('tmdb_id')->sortByDesc(fn ($votes) => $votes->count())->first();

        return response()->json([
            'activity' => $activity->forUsers($followingIds, 8),
            'rooms' => $rooms,
            'next_night' => $night ? [
                'id' => $night->id,
                'title' => $night->title,
                'scheduled_at' => $night->scheduled_at->toJSON(),
                'guests' => MemberResource::collection($night->invites->where('status', 'accepted')->pluck('user')->filter()->values()),
                'leader' => $leader ? ['tmdb_id' => $leader->first()->tmdb_id, 'votes' => $leader->count()] : null,
            ] : null,
        ]);
    }
}

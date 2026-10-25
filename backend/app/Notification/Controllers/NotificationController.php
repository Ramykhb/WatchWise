<?php

namespace App\Notification\Controllers;

use App\Support\Controller;
use App\Auth\CurrentUser\CurrentUser;
use App\Notification\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** The signed-in user's notifications (header bell and the Notifications page). */
class NotificationController extends Controller
{
    /** GET /api/notifications — the latest 50, newest first. */
    public function index(CurrentUser $current): JsonResponse
    {
        $user = $current->user();
        $notifications = $user->notifications()->latest('created_at')->latest('id')->limit(50)->get();

        return response()->json([
            'notifications' => NotificationResource::collection($this->withActors($notifications)),
            'unread' => $user->notifications()->whereNull('read_at')->count(),
        ]);
    }

    /** PATCH /api/notifications/{notification} — `read` true or false. */
    public function update(Request $request, Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);
        $read = $request->validate(['read' => ['required', 'boolean']])['read'];

        $notification->forceFill(['read_at' => $read ? ($notification->read_at ?? now()) : null])->save();

        return response()->json(['notification' => new NotificationResource($this->withActors(collect([$notification]))->first())]);
    }

    /** POST /api/notifications/read-all */
    public function readAll(CurrentUser $current): JsonResponse
    {
        $current->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }

    /** DELETE /api/notifications/{notification} */
    public function destroy(Notification $notification): JsonResponse
    {
        Gate::authorize('delete', $notification);
        $notification->delete();

        return response()->json(['message' => 'Notification dismissed.']);
    }

    /**
     * Loads the user each notification is about (data.actor_id) in one query, and whether the
     * recipient already follows them (so "Follow back" shows "Following" after a reload).
     *
     * @param  Collection<int, Notification>  $notifications
     * @return Collection<int, Notification>
     */
    private function withActors(Collection $notifications): Collection
    {
        $actorIds = $notifications->pluck('data.actor_id')->filter()->unique()->values();
        $actors = User::whereIn('id', $actorIds)->get()->keyBy('id');
        // All notifications here belong to one recipient.
        $recipientId = $notifications->first()?->user_id;
        $followed = $recipientId
            ? DB::table('follows')->where('follower_id', $recipientId)->whereIn('following_id', $actorIds)->pluck('following_id')->all()
            : [];

        return $notifications->each(function (Notification $n) use ($actors, $followed) {
            $actorId = $n->data['actor_id'] ?? 0;
            $n->setRelation('actor', $actors->get($actorId));
            $n->setRelation('actorFollowed', in_array($actorId, $followed));
        });
    }
}

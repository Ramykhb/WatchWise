<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
     * Loads the user each notification is about (data.actor_id) in one query.
     *
     * @param  Collection<int, Notification>  $notifications
     * @return Collection<int, Notification>
     */
    private function withActors(Collection $notifications): Collection
    {
        $actors = User::whereIn('id', $notifications->pluck('data.actor_id')->filter()->unique())->get()->keyBy('id');

        return $notifications->each(fn (Notification $n) => $n->setRelation('actor', $actors->get($n->data['actor_id'] ?? 0)));
    }
}

<?php

namespace App\Notification\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Notification;
use App\Notification\FormRequests\UpdateNotificationRequest;
use App\Notification\InterfaceServices\NotificationFeedServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The header bell and the Notifications page. */
class NotificationController
{
    public function __construct(private readonly NotificationFeedServiceInterface $feed) {}

    /** GET /api/notifications — your notifications, newest first. */
    public function index(CurrentUser $current): JsonResponse
    {
        return response()->json($this->feed->feed($current->user()));
    }

    /** PATCH /api/notifications/{notification} — `read`: true or false. */
    public function update(UpdateNotificationRequest $request, Notification $notification): JsonResponse
    {
        $data = $request->toDto();
        $this->feed->markRead($notification, $data->read);

        return response()->json(['read' => $data->read]);
    }

    /** POST /api/notifications/read-all */
    public function readAll(CurrentUser $current): JsonResponse
    {
        $this->feed->markAllRead($current->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /** DELETE /api/notifications/{notification} — dismissing a follow request declines it. */
    public function destroy(Notification $notification): JsonResponse
    {
        Gate::authorize('delete', $notification);

        $this->feed->dismiss($notification);

        return response()->json(['message' => 'Notification dismissed.']);
    }
}

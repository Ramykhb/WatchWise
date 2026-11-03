<?php

namespace App\Notification\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Notification;

/**
 * Members see and change only their own feed notifications. Room read receipts share the table but are never
 * touched through the notifications API.
 */
class NotificationPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    public function update(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    public function delete(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    private function owns(CurrentUser $current, Notification $notification): bool
    {
        return $current->isAuthenticated()
            && $current->id() === $notification->user_id
            && in_array($notification->type, Notification::FEED_TYPES, true);
    }
}

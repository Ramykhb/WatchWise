<?php

namespace App\Notification\Services;

use App\Models\Notification;
use App\Notification\Events\NotificationPushed;
use App\Notification\Events\NotificationsChanged;

/**
 * Keeps the owner's open tabs in step over Reverb (`notifications.{id}`). A failed push only delays things:
 * the notification is stored, and the bell catches up the next time it loads.
 */
class LiveNotifications
{
    public function __construct(private readonly NotificationPresenter $presenter) {}

    /** A new or updated notification; `silent` ones update the list without a pop-up. */
    public function push(Notification $notification, bool $silent = false): void
    {
        $response = $this->presenter->presentOne($notification);

        if ($response !== null) {
            $this->broadcast(new NotificationPushed((int) $notification->user_id, $response, $silent));
        }
    }

    /** Something the list shows changed in a way a single item can't describe (read, dismissed, withdrawn). */
    public function changed(int $userId): void
    {
        $this->broadcast(new NotificationsChanged($userId));
    }

    private function broadcast(object $event): void
    {
        rescue(fn () => event($event));
    }
}

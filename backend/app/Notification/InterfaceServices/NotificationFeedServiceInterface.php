<?php

namespace App\Notification\InterfaceServices;

use App\Models\Notification;
use App\Models\User;
use App\Notification\DTOs\Responses\NotificationFeedResponse;

/** The bell and the Notifications page: reading, marking and dismissing your own notifications. */
interface NotificationFeedServiceInterface
{
    public function feed(User $user): NotificationFeedResponse;

    public function markRead(Notification $notification, bool $read): void;

    public function markAllRead(User $user): void;

    /** Dismissing a follow request declines it. */
    public function dismiss(Notification $notification): void;
}

<?php

namespace App\Notification\Services;

use App\Models\Notification;
use App\Models\User;
use App\Notification\DTOs\Responses\NotificationFeedResponse;
use App\Notification\InterfaceRepository\NotificationRepositoryInterface;
use App\Notification\InterfaceServices\NotificationFeedServiceInterface;

class NotificationFeedService implements NotificationFeedServiceInterface
{
    /** Limits ordinary history; pending follow requests remain visible until answered. */
    private const FEED_LIMIT = 100;

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly NotificationPresenter $presenter,
        private readonly LiveNotifications $live,
    ) {}

    public function feed(User $user): NotificationFeedResponse
    {
        return new NotificationFeedResponse($this->presenter->present($this->notifications->feed($user->id, self::FEED_LIMIT), $user->id));
    }

    public function markRead(Notification $notification, bool $read): void
    {
        $this->notifications->setRead([$notification->id], $read);
        $this->live->changed((int) $notification->user_id);
    }

    public function markAllRead(User $user): void
    {
        if ($this->notifications->markAllRead($user->id) > 0) {
            $this->live->changed($user->id);
        }
    }

    public function dismiss(Notification $notification): void
    {
        $this->notifications->delete([$notification->id]);
        $this->live->changed((int) $notification->user_id);
    }
}

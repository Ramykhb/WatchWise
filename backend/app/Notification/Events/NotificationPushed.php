<?php

namespace App\Notification\Events;

use App\Notification\DTOs\Responses\NotificationResponse;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A notification was created or brought up to date (a room's "new messages" counting up), pushed to its owner's
 * open tabs on `notifications.{id}`. The browser puts it at the top of the list and, unless `silent`, pops it up.
 */
class NotificationPushed implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $userId,
        public readonly NotificationResponse $notification,
        public readonly bool $silent = false,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("notifications.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array{notification: array<string, mixed>, silent: bool}
     */
    public function broadcastWith(): array
    {
        return ['notification' => $this->notification->jsonSerialize(), 'silent' => $this->silent];
    }
}

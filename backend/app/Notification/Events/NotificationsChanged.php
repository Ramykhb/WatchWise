<?php

namespace App\Notification\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Notifications were read, dismissed, answered or withdrawn (e.g. a follow request cancelled by its sender):
 * the owner's open tabs load the list again.
 */
class NotificationsChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly int $userId) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("notifications.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notifications.changed';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array{user_id: int}
     */
    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId];
    }
}

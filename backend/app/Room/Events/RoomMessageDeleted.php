<?php

namespace App\Room\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Staff removed a message; whoever has the room open drops it, and it carries nothing of the message or of who removed it. */
class RoomMessageDeleted implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $roomId,
        public readonly int $messageId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array{room_id: int, message_id: int}
     */
    public function broadcastWith(): array
    {
        return ['room_id' => $this->roomId, 'message_id' => $this->messageId];
    }
}

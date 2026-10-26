<?php

namespace App\Room\Events;

use App\Room\DTOs\Responses\RoomMessageResponse;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A new message, pushed to everyone who has the room open; members among them confirm they read it. */
class RoomMessageSent implements ShouldBroadcastNow
{
    public function __construct(public readonly RoomMessageResponse $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->message->message->room_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->message->jsonSerialize();
    }
}

<?php

namespace App\Room\Events;

use App\Room\DTOs\Responses\RoomMessageResponse;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A new message, pushed to everyone who has the room open (members among them confirm they read it) and to
 * the room-list channels, so the Rooms list shows it as the room's latest message.
 */
class RoomMessageSent implements ShouldBroadcastNow
{
    /**
     * @param  list<PrivateChannel>  $listChannels
     */
    public function __construct(
        public readonly RoomMessageResponse $message,
        public readonly array $listChannels,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->message->message->room_id), ...$this->listChannels];
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

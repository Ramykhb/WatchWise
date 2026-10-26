<?php

namespace App\Room\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A room was created, or someone joined it, was invited to it or declined: the Rooms lists that show it reload. */
class RoomsChanged implements ShouldBroadcastNow
{
    /**
     * @param  list<PrivateChannel>  $listChannels
     */
    public function __construct(
        public readonly int $roomId,
        public readonly array $listChannels,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->listChannels;
    }

    public function broadcastAs(): string
    {
        return 'rooms.changed';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array{room_id: int}
     */
    public function broadcastWith(): array
    {
        return ['room_id' => $this->roomId];
    }
}

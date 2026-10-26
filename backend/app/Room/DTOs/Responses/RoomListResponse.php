<?php

namespace App\Room\DTOs\Responses;

use JsonSerializable;

/** GET /api/rooms: the rooms the viewer can see: theirs first, then invitations, then public rooms, newest activity first. */
final readonly class RoomListResponse implements JsonSerializable
{
    /**
     * @param  list<RoomResponse>  $rooms
     */
    public function __construct(public array $rooms) {}

    /**
     * @return array{rooms: list<RoomResponse>}
     */
    public function jsonSerialize(): array
    {
        return ['rooms' => $this->rooms];
    }
}

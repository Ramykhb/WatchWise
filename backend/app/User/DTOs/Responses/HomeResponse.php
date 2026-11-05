<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** The signed-in Home page: followed activity, busy rooms and the next movie night. */
final readonly class HomeResponse implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $rooms
     * @param  array<string, mixed>|null  $nextNight
     * @param  list<array<string, mixed>>  $activity
     */
    public function __construct(
        public array $rooms,
        public ?array $nextNight,
        public array $activity = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'activity' => $this->activity,
            'rooms' => $this->rooms,
            'next_night' => $this->nextNight,
        ];
    }
}

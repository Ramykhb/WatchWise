<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** The signed-in Home page: busy rooms and the user's next movie night. */
final readonly class HomeResponse implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $rooms
     * @param  array<string, mixed>|null  $nextNight
     */
    public function __construct(
        public array $rooms,
        public ?array $nextNight,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'rooms' => $this->rooms,
            'next_night' => $this->nextNight,
        ];
    }
}

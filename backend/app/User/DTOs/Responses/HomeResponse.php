<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** The signed-in Home page: followed activity, busy rooms, the next movie night and the films admins featured. */
final readonly class HomeResponse implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $rooms
     * @param  array<string, mixed>|null  $nextNight
     * @param  list<array<string, mixed>>  $activity
     * @param  list<array<string, mixed>>  $featured  movie cards, shaped like the movies of GET /api/movies
     */
    public function __construct(
        public array $rooms,
        public ?array $nextNight,
        public array $activity = [],
        public array $featured = [],
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
            'featured' => $this->featured,
        ];
    }
}

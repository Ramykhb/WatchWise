<?php

namespace App\MovieNight\DTOs\Responses;

use JsonSerializable;

/** The user's movie nights grouped for the Movie Nights page. */
final readonly class MovieNightOverviewResponse implements JsonSerializable
{
    /**
     * @param  list<MovieNightResponse>  $upcoming  accepted, maybe and hosted nights that have not finished
     * @param  list<MovieNightResponse>  $invitations  unanswered invitations to nights that have not finished
     * @param  list<MovieNightResponse>  $past  finished nights, newest first
     */
    public function __construct(
        public array $upcoming,
        public array $invitations,
        public array $past,
    ) {}

    /**
     * @return array<string, list<MovieNightResponse>>
     */
    public function jsonSerialize(): array
    {
        return [
            'upcoming' => $this->upcoming,
            'invitations' => $this->invitations,
            'past' => $this->past,
        ];
    }
}

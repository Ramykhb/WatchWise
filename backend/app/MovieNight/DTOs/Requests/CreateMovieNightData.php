<?php

namespace App\MovieNight\DTOs\Requests;

final readonly class CreateMovieNightData
{
    /**
     * @param  list<string>  $invitees  usernames
     * @param  list<int>  $tmdbIds  the starting shortlist
     */
    public function __construct(
        public string $title,
        public string $scheduledAt,
        public ?string $location,
        public ?string $meetingLink,
        public ?string $note,
        public array $invitees,
        public array $tmdbIds,
    ) {}

    /**
     * @param  array{title: string, scheduled_at: string, location?: string|null, meeting_link?: string|null, note?: string|null, invitees?: list<string>, tmdb_ids?: list<int>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['title'],
            $validated['scheduled_at'],
            $validated['location'] ?? null,
            $validated['meeting_link'] ?? null,
            $validated['note'] ?? null,
            $validated['invitees'] ?? [],
            $validated['tmdb_ids'] ?? [],
        );
    }
}

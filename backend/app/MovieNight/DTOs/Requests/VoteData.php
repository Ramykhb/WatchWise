<?php

namespace App\MovieNight\DTOs\Requests;

final readonly class VoteData
{
    public function __construct(public int $tmdbId) {}

    /**
     * @param  array{tmdb_id: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self((int) $validated['tmdb_id']);
    }
}

<?php

namespace App\Watchlist\DTOs\Requests;

final readonly class AddToWatchlistData
{
    /**
     * Where a film stands in the user's watchlist.
     *
     * @var list<string>
     */
    public const STATUSES = ['to_watch', 'watching', 'watched'];

    /**
     * @param  string|null  $status  null keeps the film's current status, or `to_watch` for a new film
     */
    public function __construct(
        public int $tmdbId,
        public ?string $status,
    ) {}

    /**
     * @param  array{tmdb_id: int|string, status?: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self((int) $validated['tmdb_id'], $validated['status'] ?? null);
    }
}

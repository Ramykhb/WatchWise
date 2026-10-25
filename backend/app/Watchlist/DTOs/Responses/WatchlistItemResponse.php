<?php

namespace App\Watchlist\DTOs\Responses;

use App\Models\Watchlist;
use JsonSerializable;

/** One film in the user's watchlist. */
final readonly class WatchlistItemResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $tmdbId,
        public string $status,
        public ?string $watchedAt,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Watchlist $item): self
    {
        return new self(
            id: $item->id,
            tmdbId: $item->tmdb_id,
            status: $item->status,
            watchedAt: $item->watched_at?->toJSON(),
            createdAt: $item->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdbId,
            'status' => $this->status,
            'watched_at' => $this->watchedAt,
            'created_at' => $this->createdAt,
        ];
    }
}

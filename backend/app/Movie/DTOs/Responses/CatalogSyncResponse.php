<?php

namespace App\Movie\DTOs\Responses;

use JsonSerializable;

/** How the local catalogue stands: films cached from TMDB, how many an admin overrode, and when one was last synced. */
final readonly class CatalogSyncResponse implements JsonSerializable
{
    public function __construct(
        public int $cached,
        public int $overrides,
        public ?string $lastSyncedAt,
    ) {}

    /**
     * @return array{cached: int, overrides: int, last_synced_at: string|null}
     */
    public function jsonSerialize(): array
    {
        return ['cached' => $this->cached, 'overrides' => $this->overrides, 'last_synced_at' => $this->lastSyncedAt];
    }
}

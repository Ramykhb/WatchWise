<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** How much of the watched and rated movies a genre accounts for. */
final readonly class GenreShareResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public int $count,
        public float $share,
    ) {}

    /**
     * @return array{id: int, name: string, count: int, share: float}
     */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'count' => $this->count, 'share' => $this->share];
    }
}

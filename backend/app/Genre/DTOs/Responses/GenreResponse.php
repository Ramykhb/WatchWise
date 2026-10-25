<?php

namespace App\Genre\DTOs\Responses;

use JsonSerializable;

final readonly class GenreResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @return array{id: int, name: string}
     */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}

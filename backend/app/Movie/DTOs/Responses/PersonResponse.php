<?php

namespace App\Movie\DTOs\Responses;

use JsonSerializable;

/** A crew member shown by name, e.g. a director. */
final readonly class PersonResponse implements JsonSerializable
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

<?php

namespace App\Room\DTOs\Requests;

final readonly class CreateRoomData
{
    public function __construct(
        public string $name,
        public int $tmdbId,
        public bool $spoilers,
    ) {}

    /**
     * @param  array{name: string, tmdb_id: int|string, spoilers?: bool}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['name'], (int) $validated['tmdb_id'], (bool) ($validated['spoilers'] ?? false));
    }
}

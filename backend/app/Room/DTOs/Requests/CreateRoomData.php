<?php

namespace App\Room\DTOs\Requests;

use App\Support\MediaType;

final readonly class CreateRoomData
{
    public function __construct(
        public string $name,
        public int $tmdbId,
        public bool $spoilers,
        public bool $isPrivate,
        public string $mediaType = MediaType::MOVIE,
    ) {}

    /**
     * @param  array{name: string, tmdb_id: int|string, media_type?: string, spoilers?: bool, is_private?: bool}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['name'],
            (int) $validated['tmdb_id'],
            (bool) ($validated['spoilers'] ?? false),
            (bool) ($validated['is_private'] ?? false),
            $validated['media_type'] ?? MediaType::MOVIE,
        );
    }
}

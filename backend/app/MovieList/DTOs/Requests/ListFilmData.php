<?php

namespace App\MovieList\DTOs\Requests;

use App\Support\MediaType;

/** One title in a list: a TMDB id and whether it is a movie or a TV show. */
final readonly class ListFilmData
{
    /** How list requests write a title: 550 for a movie, "tv-1399" for a TV show. */
    public const KEY_PATTERN = '/^(tv-)?[1-9][0-9]*$/';

    public function __construct(
        public int $tmdbId,
        public string $mediaType = MediaType::MOVIE,
    ) {}

    /**
     * A title as list requests send it: 550 for a movie, "tv-1399" for a TV show (the format
     * MediaType::key() writes and the frontend uses in its URLs).
     */
    public static function fromKey(int|string $key): self
    {
        $key = (string) $key;

        return str_starts_with($key, 'tv-')
            ? new self((int) substr($key, 3), MediaType::TV)
            : new self((int) $key);
    }

    /** "550" or "tv-1399", the same title written the way fromKey() reads it. */
    public function key(): string
    {
        return MediaType::key($this->mediaType, $this->tmdbId);
    }
}

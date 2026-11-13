<?php

namespace App\MovieList\DTOs\Requests;

use App\Support\MediaType;
use Closure;

/** One title in a list: a TMDB id and whether it is a movie or a TV show. */
final readonly class ListFilmData
{
    /** How list requests write a title: 550 for a movie, "tv-1399" for a TV show. */
    public const KEY_PATTERN = '/^(tv-)?[1-9][0-9]{0,9}$/';

    /** The largest TMDB id accepted (signed 32-bit max): far above any real TMDB id, and safe to cast to int. */
    public const MAX_ID = 2147483647;

    /**
     * Rules for one title written as a key (550 or "tv-1399"): the pattern allows ten digits,
     * so the id itself is checked against MAX_ID too.
     *
     * @return list<mixed>
     */
    public static function keyRules(): array
    {
        return [
            'distinct',
            'regex:'.self::KEY_PATTERN,
            fn (string $attribute, mixed $value, Closure $fail) => (int) preg_replace('/^tv-/', '', (string) $value) > self::MAX_ID
                ? $fail('The :attribute is too large.')
                : null,
        ];
    }

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

<?php

namespace App\Review\DTOs\Requests;

/** The signed-in member's review of a title, written on the Movie page. */
final readonly class SaveReviewData
{
    public const MAX_LENGTH = 2000;

    public function __construct(
        public int $tmdbId,
        public string $mediaType,
        public string $content,
        public int $rating,
        public bool $hasSpoilers,
    ) {}

    /**
     * @param  array{tmdb_id: int|string, type?: string, content: string, rating: int|string, has_spoilers?: bool}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            (int) $validated['tmdb_id'],
            $validated['type'] ?? 'movie',
            trim($validated['content']),
            (int) $validated['rating'],
            (bool) ($validated['has_spoilers'] ?? false),
        );
    }
}

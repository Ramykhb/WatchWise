<?php

namespace App\Review\DTOs\Requests;

/** One page of a title's reviews on the Movie page. */
final readonly class MovieReviewQuery
{
    /** @var list<string> */
    public const SORTS = ['helpful', 'newest', 'highest', 'lowest'];

    public const PER_PAGE = 10;

    public function __construct(
        public int $tmdbId,
        public string $mediaType = 'movie',
        public string $sort = 'helpful',
        public int $page = 1,
    ) {}

    /**
     * @param  array{tmdb_id: int|string, type?: string, sort?: string, page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            (int) $validated['tmdb_id'],
            $validated['type'] ?? 'movie',
            $validated['sort'] ?? 'helpful',
            (int) ($validated['page'] ?? 1),
        );
    }
}

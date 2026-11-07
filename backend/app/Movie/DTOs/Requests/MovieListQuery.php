<?php

namespace App\Movie\DTOs\Requests;

final readonly class MovieListQuery
{
    /**
     * The TMDB movie lists the frontend can ask for.
     *
     * @var list<string>
     */
    public const LISTS = ['popular', 'top_rated', 'now_playing', 'upcoming'];

    public function __construct(
        public string $list = 'popular',
        public int $page = 1,
        /** Upcoming films: most anticipated first, or earliest release first. */
        public string $sort = 'popularity',
    ) {}

    /**
     * @param  array{list?: string, page?: int|string, sort?: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['list'] ?? 'popular', (int) ($validated['page'] ?? 1), $validated['sort'] ?? 'popularity');
    }
}

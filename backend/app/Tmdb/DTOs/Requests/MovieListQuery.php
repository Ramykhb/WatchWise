<?php

namespace App\Tmdb\DTOs\Requests;

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
    ) {}

    /**
     * @param  array{list?: string, page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['list'] ?? 'popular', (int) ($validated['page'] ?? 1));
    }
}

<?php

namespace App\Tmdb\DTOs\Requests;

final readonly class MovieSearchQuery
{
    public function __construct(
        public string $query,
        public int $page = 1,
        public ?int $year = null,
        /** What to search: 'movie', 'tv' or 'all' (both, merged). */
        public string $type = 'movie',
    ) {}

    /**
     * The same search for another page.
     */
    public function withPage(int $page): self
    {
        return new self($this->query, $page, $this->year, $this->type);
    }

    /**
     * @param  array{q: string, page?: int|string, year?: int|string, type?: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['q'],
            (int) ($validated['page'] ?? 1),
            isset($validated['year']) ? (int) $validated['year'] : null,
            $validated['type'] ?? 'movie',
        );
    }
}

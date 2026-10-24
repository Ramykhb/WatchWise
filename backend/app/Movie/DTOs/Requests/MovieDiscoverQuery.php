<?php

namespace App\Movie\DTOs\Requests;

final readonly class MovieDiscoverQuery
{
    /**
     * The sort options Discover offers. The repository maps each one to a TMDB sort.
     *
     * @var list<string>
     */
    public const SORTS = ['popularity', 'rating', 'release_date', 'title'];

    public function __construct(
        /** Comma separated TMDB genre ids, e.g. "28,12". */
        public ?string $genres = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        /** Minimum rating on the 0-5 scale the API exposes. */
        public ?float $minRating = null,
        public ?int $runtimeMin = null,
        public ?int $runtimeMax = null,
        /** Two letter original language code. */
        public ?string $language = null,
        public string $sort = 'popularity',
        public int $page = 1,
        /** What to browse: 'movie', 'tv' or 'all' (both, merged). */
        public string $type = 'movie',
        /** TV genre ids for a combined ('all') browse, where `genres` holds the movie ones. */
        public ?string $tvGenres = null,
    ) {}

    /**
     * The same browse for another page.
     */
    public function withPage(int $page): self
    {
        return new self(
            $this->genres,
            $this->yearFrom,
            $this->yearTo,
            $this->minRating,
            $this->runtimeMin,
            $this->runtimeMax,
            $this->language,
            $this->sort,
            $page,
            $this->type,
            $this->tvGenres,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            genres: $validated['genres'] ?? null,
            yearFrom: isset($validated['year_from']) ? (int) $validated['year_from'] : null,
            yearTo: isset($validated['year_to']) ? (int) $validated['year_to'] : null,
            minRating: isset($validated['min_rating']) ? (float) $validated['min_rating'] : null,
            runtimeMin: isset($validated['runtime_min']) ? (int) $validated['runtime_min'] : null,
            runtimeMax: isset($validated['runtime_max']) ? (int) $validated['runtime_max'] : null,
            language: $validated['language'] ?? null,
            sort: $validated['sort'] ?? 'popularity',
            page: (int) ($validated['page'] ?? 1),
            type: $validated['type'] ?? 'movie',
            tvGenres: $validated['tv_genres'] ?? null,
        );
    }
}

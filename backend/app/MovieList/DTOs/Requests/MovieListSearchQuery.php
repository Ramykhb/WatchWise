<?php

namespace App\MovieList\DTOs\Requests;

use App\Support\MediaType;

final readonly class MovieListSearchQuery
{
    /**
     * Whose lists to show: the user's own, other members' public lists, or public lists by people they follow.
     *
     * @var list<string>
     */
    public const SCOPES = ['mine', 'public', 'following'];

    /** Lists per page when `per_page` is not given: a member's own lists are browsed in bulk, other lists in smaller batches. */
    public const DEFAULT_PER_PAGE_MINE = 100;

    public const DEFAULT_PER_PAGE = 30;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        public string $scope = 'mine',
        public string $q = '',
        /** A TMDB id: each list then says whether it already holds that title (`has_movie`). */
        public ?int $contains = null,
        /** Whether `contains` is a movie or a TV show. */
        public string $containsType = MediaType::MOVIE,
        /** 1-based page number. */
        public int $page = 1,
        /** Lists per page; null means the scope's default (see `perPage()`). */
        public ?int $perPage = null,
    ) {}

    /** Lists on one page: the requested size, else 100 for `mine` and 30 for the other scopes. */
    public function perPage(): int
    {
        return $this->perPage ?? ($this->scope === 'mine' ? self::DEFAULT_PER_PAGE_MINE : self::DEFAULT_PER_PAGE);
    }

    /**
     * @param  array{scope?: string, q?: string|null, contains?: int|string|null, contains_type?: string, page?: int|string, per_page?: int|string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $contains = $validated['contains'] ?? null;

        return new self(
            $validated['scope'] ?? 'mine',
            trim((string) ($validated['q'] ?? '')),
            $contains === null ? null : (int) $contains,
            $validated['contains_type'] ?? MediaType::MOVIE,
            (int) ($validated['page'] ?? 1),
            isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        );
    }
}

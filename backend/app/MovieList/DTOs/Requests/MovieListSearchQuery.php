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

    public function __construct(
        public string $scope = 'mine',
        public string $q = '',
        /** A TMDB id: each list then says whether it already holds that title (`has_movie`). */
        public ?int $contains = null,
        /** Whether `contains` is a movie or a TV show. */
        public string $containsType = MediaType::MOVIE,
    ) {}

    /**
     * @param  array{scope?: string, q?: string|null, contains?: int|string|null, contains_type?: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $contains = $validated['contains'] ?? null;

        return new self(
            $validated['scope'] ?? 'mine',
            trim((string) ($validated['q'] ?? '')),
            $contains === null ? null : (int) $contains,
            $validated['contains_type'] ?? MediaType::MOVIE,
        );
    }
}

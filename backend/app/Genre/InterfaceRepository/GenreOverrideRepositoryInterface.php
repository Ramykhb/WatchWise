<?php

namespace App\Genre\InterfaceRepository;

use App\Models\GenreOverride;
use Illuminate\Support\Collection;

/** The local overrides staff layer over TMDB's genres. */
interface GenreOverrideRepositoryInterface
{
    /**
     * Every override of the movie or tv genres.
     *
     * @param  'movie'|'tv'  $type
     * @return Collection<int, GenreOverride> keyed by TMDB id
     */
    public function forType(string $type): Collection;

    /**
     * With `$lockForUpdate` the row stays locked until the surrounding transaction ends (call inside one).
     *
     * @param  'movie'|'tv'  $type
     */
    public function find(string $type, int $tmdbId, bool $lockForUpdate = false): ?GenreOverride;

    /**
     * Creates the override or replaces the values of the existing one.
     *
     * @param  'movie'|'tv'  $type
     * @param  array{name: string|null, is_hidden: bool, position: int|null}  $values
     */
    public function save(string $type, int $tmdbId, array $values, ?int $updatedBy): GenreOverride;

    public function delete(GenreOverride $override): void;
}

<?php

namespace App\Genre\InterfaceServices;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Models\GenreOverride;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Support\Collection;

/**
 * TMDB's genres with the local overrides staff layer on top. TMDB's list is cached for a day and the overrides
 * are read on every call, after the cache, so a change shows on the next request.
 */
interface GenreServiceInterface
{
    /**
     * The genres offered to members: display names applied, hidden genres dropped, those with a position first
     * (by position), the rest in TMDB's order (a rename does not move a genre). A hidden genre is only left out of this list; its id still works everywhere else.
     *
     * @param  'movie'|'tv'  $type
     * @return list<GenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function all(string $type = 'movie'): array;

    /**
     * Every genre with its display name applied, hidden ones included, in the order of `all()`. For code that has
     * to name a genre it finds on a film whether or not members may pick it.
     *
     * @param  'movie'|'tv'  $type
     * @return list<GenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function allIncludingHidden(string $type = 'movie'): array;

    /**
     * TMDB's own list and names, as cached: no override applied and nothing dropped.
     *
     * @param  'movie'|'tv'  $type
     * @return list<GenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function tmdb(string $type = 'movie'): array;

    /**
     * @param  'movie'|'tv'  $type
     * @return Collection<int, GenreOverride> keyed by TMDB id
     */
    public function overrides(string $type): Collection;

    /**
     * @param  'movie'|'tv'  $type
     * @param  bool  $lockForUpdate  keep the row locked until the surrounding transaction ends (call inside one)
     */
    public function override(string $type, int $tmdbId, bool $lockForUpdate = false): ?GenreOverride;

    /**
     * Stores the override. Values that are all the default (no name, not hidden, no position) are the same as
     * having no override, so the row is removed instead and null is returned.
     *
     * @param  'movie'|'tv'  $type
     * @param  array{name: string|null, is_hidden: bool, position: int|null}  $values
     */
    public function saveOverride(string $type, int $tmdbId, array $values, ?int $updatedBy): ?GenreOverride;

    public function removeOverride(GenreOverride $override): void;
}

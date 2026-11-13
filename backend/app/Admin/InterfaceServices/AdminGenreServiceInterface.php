<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\AdminGenreQuery;
use App\Admin\DTOs\Requests\UpdateGenreOverrideData;
use App\Admin\DTOs\Responses\AdminGenreResponse;
use App\Genre\Exceptions\GenreNotFound;
use App\Models\User;
use App\Support\Exceptions\TmdbUnavailable;

/** Staff's view of TMDB's genres and the local overrides layered on them. */
interface AdminGenreServiceInterface
{
    /**
     * Every TMDB genre of the type merged with its override, in TMDB's order.
     *
     * @return list<AdminGenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function list(AdminGenreQuery $query): array;

    /**
     * Changes the override of one genre and records what really changed; sending the values it already has writes
     * and records nothing. An override left with no name, no position and not hidden is removed.
     *
     * @param  'movie'|'tv'  $type
     *
     * @throws GenreNotFound when TMDB's list has no such genre
     * @throws TmdbUnavailable
     */
    public function update(User $admin, string $type, int $tmdbId, UpdateGenreOverrideData $data): AdminGenreResponse;

    /**
     * Removes the override so the genre shows as TMDB has it. Nothing is recorded when there was none.
     *
     * @param  'movie'|'tv'  $type
     */
    public function reset(User $admin, string $type, int $tmdbId): void;
}

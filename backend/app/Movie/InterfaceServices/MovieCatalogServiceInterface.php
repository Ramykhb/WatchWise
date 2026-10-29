<?php

namespace App\Movie\InterfaceServices;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\DTOs\Responses\CatalogSyncResponse;
use App\Movie\Exceptions\MovieNotFound;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** The local copy of the films members have watchlisted, rated, reviewed or listed. */
interface MovieCatalogServiceInterface
{
    /**
     * Stores the film the first time it is seen. Does nothing when it is already stored or TMDB is unreachable.
     */
    public function remember(int $tmdbId): void;

    /** @return LengthAwarePaginator<int, LocalMovie> */
    public function paginate(LocalMovieQuery $query): LengthAwarePaginator;

    /** @throws MovieNotFound when the film was never stored */
    public function update(int $tmdbId, UpdateLocalMovieData $data): LocalMovie;

    /**
     * @param  list<int>  $tmdbIds
     * @return Collection<int, LocalMovie> keyed by TMDB id
     */
    public function summaries(array $tmdbIds): Collection;

    /** How many films are cached, how many an admin edited, and when one was last synced from TMDB. */
    public function syncSummary(): CatalogSyncResponse;

    /**
     * Films admins have hidden; the app's own lists and stats leave them out.
     *
     * @return list<int>
     */
    public function hiddenTmdbIds(): array;
}

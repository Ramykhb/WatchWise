<?php

namespace App\Movie\InterfaceServices;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\DTOs\Responses\CatalogSyncResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Support\HiddenMovies;
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
     * Films admins have hidden from browsing; admin stats leave them out.
     *
     * @return list<int>
     */
    public function hiddenTmdbIds(): array;

    /** The same films as a filter for the lists members browse: search, Discover, Home, recommendations and other members' lists. */
    public function hiddenMovies(): HiddenMovies;

    /**
     * How many visible (not hidden) catalogue films carry each genre id.
     *
     * @return array<int, int> film count keyed by genre id; genres no film carries are absent
     */
    public function visibleGenreCounts(): array;

    /**
     * Visible films an admin featured, most recently edited first, for the Home page's Featured row.
     *
     * @return Collection<int, LocalMovie>
     */
    public function featured(int $limit): Collection;
}

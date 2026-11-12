<?php

namespace App\Movie\InterfaceRepository;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Support\HiddenMovies;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MovieCatalogRepositoryInterface
{
    /** @return LengthAwarePaginator<int, LocalMovie> */
    public function paginate(LocalMovieQuery $query): LengthAwarePaginator;

    /** With `$lockForUpdate` the row stays locked until the surrounding transaction ends (call inside one). */
    public function findByTmdbId(int $tmdbId, bool $lockForUpdate = false): ?LocalMovie;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LocalMovie;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(LocalMovie $movie, array $changes): LocalMovie;

    /**
     * @param  list<int>  $tmdbIds
     * @return Collection<int, LocalMovie> keyed by TMDB id
     */
    public function findManyByTmdbId(array $tmdbIds): Collection;

    /** @return list<int> */
    public function hiddenTmdbIds(): array;

    /** The hidden films as the filter every member-facing list uses. */
    public function hidden(): HiddenMovies;

    /**
     * Visible films an admin featured, most recently edited first.
     *
     * @return Collection<int, LocalMovie>
     */
    public function featured(int $limit): Collection;

    /**
     * How many visible (not hidden) films carry each genre id.
     *
     * @return array<int, int> film count keyed by genre id; genres no film carries are absent
     */
    public function visibleGenreCounts(): array;

    /**
     * @return array{cached: int, overrides: int, last_synced_at: CarbonInterface|null}
     */
    public function syncTotals(): array;
}

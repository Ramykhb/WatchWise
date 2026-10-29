<?php

namespace App\Movie\InterfaceRepository;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MovieCatalogRepositoryInterface
{
    /** @return LengthAwarePaginator<int, LocalMovie> */
    public function paginate(LocalMovieQuery $query): LengthAwarePaginator;

    public function findByTmdbId(int $tmdbId): ?LocalMovie;

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

    /**
     * @return array{cached: int, overrides: int, last_synced_at: CarbonInterface|null}
     */
    public function syncTotals(): array;
}

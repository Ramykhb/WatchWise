<?php

namespace App\Movie\Services;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\DTOs\Responses\CatalogSyncResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Movie\InterfaceRepository\MovieCatalogRepositoryInterface;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MovieCatalogService implements MovieCatalogServiceInterface
{
    public function __construct(
        private readonly MovieCatalogRepositoryInterface $catalog,
        private readonly MovieServiceInterface $movies,
    ) {}

    public function remember(int $tmdbId): void
    {
        if ($this->catalog->findByTmdbId($tmdbId) !== null) {
            return;
        }

        try {
            $details = $this->movies->find($tmdbId);
        } catch (MovieNotFound|TmdbUnavailable) {
            return;
        }

        $posterPath = $details->movie->posterUrl === null
            ? null
            : '/'.basename((string) parse_url($details->movie->posterUrl, PHP_URL_PATH));

        $this->catalog->create([
            'tmdb_id' => $tmdbId,
            'title' => $details->movie->title,
            'overview' => $details->movie->overview,
            'poster_path' => $posterPath,
            'release_date' => $details->movie->releaseDate,
            'runtime' => $details->runtime,
            'director' => $details->directors[0]->name ?? null,
            'genre_ids' => $details->movie->genreIds ?: array_map(fn ($genre): int => $genre->id, $details->genres),
            'synced_at' => now(),
        ]);
    }

    public function paginate(LocalMovieQuery $query): LengthAwarePaginator
    {
        return $this->catalog->paginate($query);
    }

    public function update(int $tmdbId, UpdateLocalMovieData $data): LocalMovie
    {
        $movie = $this->catalog->findByTmdbId($tmdbId) ?? throw new MovieNotFound($tmdbId);

        $changes = $data->changes();

        if ($data->editsContent()) {
            $changes['edited_at'] = now();
        }

        return $this->catalog->update($movie, $changes);
    }

    public function syncSummary(): CatalogSyncResponse
    {
        $totals = $this->catalog->syncTotals();

        return new CatalogSyncResponse($totals['cached'], $totals['overrides'], $totals['last_synced_at']?->toJSON());
    }

    public function summaries(array $tmdbIds): Collection
    {
        return $this->catalog->findManyByTmdbId($tmdbIds);
    }

    public function hiddenTmdbIds(): array
    {
        return $this->catalog->hiddenTmdbIds();
    }
}

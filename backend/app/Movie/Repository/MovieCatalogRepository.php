<?php

namespace App\Movie\Repository;

use App\Models\LocalMovie;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\InterfaceRepository\MovieCatalogRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class MovieCatalogRepository implements MovieCatalogRepositoryInterface
{
    public function paginate(LocalMovieQuery $query): LengthAwarePaginator
    {
        return LocalMovie::query()
            ->when($query->search !== '', fn ($builder) => $builder->whereRaw("title LIKE ? ESCAPE '!'", [LikePattern::contains($query->search)]))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function findByTmdbId(int $tmdbId): ?LocalMovie
    {
        return LocalMovie::where('tmdb_id', $tmdbId)->first();
    }

    public function create(array $attributes): LocalMovie
    {
        return LocalMovie::create($attributes);
    }

    public function update(LocalMovie $movie, array $changes): LocalMovie
    {
        $movie->update($changes);

        return $movie;
    }

    public function findManyByTmdbId(array $tmdbIds): Collection
    {
        return LocalMovie::whereIn('tmdb_id', $tmdbIds)->get()->keyBy('tmdb_id');
    }

    public function syncTotals(): array
    {
        $lastSynced = LocalMovie::max('synced_at');

        return [
            'cached' => LocalMovie::count(),
            'overrides' => LocalMovie::whereNotNull('edited_at')->count(),
            'last_synced_at' => $lastSynced === null ? null : Date::parse($lastSynced),
        ];
    }

    public function hiddenTmdbIds(): array
    {
        return LocalMovie::where('is_hidden', true)->pluck('tmdb_id')->map(fn ($id): int => (int) $id)->all();
    }
}

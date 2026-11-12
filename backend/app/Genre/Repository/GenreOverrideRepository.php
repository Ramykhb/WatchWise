<?php

namespace App\Genre\Repository;

use App\Genre\InterfaceRepository\GenreOverrideRepositoryInterface;
use App\Models\GenreOverride;
use Illuminate\Support\Collection;

class GenreOverrideRepository implements GenreOverrideRepositoryInterface
{
    public function forType(string $type): Collection
    {
        return GenreOverride::where('type', $type)->get()->keyBy('tmdb_id');
    }

    public function find(string $type, int $tmdbId, bool $lockForUpdate = false): ?GenreOverride
    {
        return GenreOverride::where('type', $type)
            ->where('tmdb_id', $tmdbId)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function save(string $type, int $tmdbId, array $values, ?int $updatedBy): GenreOverride
    {
        return GenreOverride::updateOrCreate(
            ['type' => $type, 'tmdb_id' => $tmdbId],
            [...$values, 'updated_by' => $updatedBy],
        );
    }

    public function delete(GenreOverride $override): void
    {
        $override->delete();
    }
}

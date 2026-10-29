<?php

namespace App\MovieList\Repository;

use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\Support\LikePattern;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

class MovieListRepository implements MovieListRepositoryInterface
{
    public function search(MovieListSearchQuery $query, User $viewer): Collection
    {
        $scope = $query->scope;

        return MovieList::with(['user', 'items'])->withCount('items')
            ->when($query->contains !== null, fn ($builder) => $builder->withExists([
                'items as has_movie' => fn ($items) => $items->where('tmdb_id', $query->contains)->where('media_type', $query->containsType),
            ]))
            ->when($query->q !== '', fn ($builder) => $builder->whereRaw("title LIKE ? ESCAPE '!'", [LikePattern::contains($query->q)]))
            ->when($scope === 'mine', fn ($builder) => $builder->where('user_id', $viewer->id))
            ->when($scope === 'public', fn ($builder) => $builder->where('is_public', true)->where('user_id', '!=', $viewer->id))
            // Public lists by people the user follows.
            ->when($scope === 'following', fn ($builder) => $builder->where('is_public', true)
                ->whereIn('user_id', $viewer->following()->select('users.id')))
            ->latest('updated_at')
            ->limit($scope === 'mine' ? 200 : 30)
            ->get();
    }

    public function create(array $attributes): MovieList
    {
        return MovieList::create($attributes);
    }

    public function update(MovieList $list, array $attributes): void
    {
        $list->fill($attributes);
        // touch() saves the new fields and bumps updated_at even when only the films changed.
        $list->touch();
    }

    public function delete(MovieList $list): void
    {
        $list->delete();
    }

    public function addItem(MovieList $list, ListFilmData $film): bool
    {
        if ($list->items()->where('tmdb_id', $film->tmdbId)->where('media_type', $film->mediaType)->exists()) {
            return false;
        }

        try {
            $list->items()->create([
                'tmdb_id' => $film->tmdbId,
                'media_type' => $film->mediaType,
                'position' => (int) $list->items()->max('position') + 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request added the same title between the check and the insert: it's there either way.
            return false;
        }
        $list->touch();

        return true;
    }

    public function removeItem(MovieList $list, ListFilmData $film): bool
    {
        $removed = $list->items()->where('tmdb_id', $film->tmdbId)->where('media_type', $film->mediaType)->delete() > 0;
        if ($removed) {
            $list->touch();
        }

        return $removed;
    }

    public function syncItems(MovieList $list, array $films): array
    {
        $wanted = array_map(fn (ListFilmData $film): string => $film->key(), $films);
        $items = $list->items()->get(['id', 'tmdb_id', 'media_type']);
        $existing = $items->map(fn (MovieListItem $item): string => MediaType::key($item->media_type, $item->tmdb_id))->all();

        // A movie and a TV show can share a TMDB id, so titles are compared by id and type together.
        $stale = $items->reject(fn (MovieListItem $item): bool => in_array(MediaType::key($item->media_type, $item->tmdb_id), $wanted, true));
        $list->items()->whereKey($stale->modelKeys())->delete();

        foreach (array_values($films) as $position => $film) {
            $list->items()->updateOrCreate(['tmdb_id' => $film->tmdbId, 'media_type' => $film->mediaType], ['position' => $position]);
        }

        return array_values(array_filter($films, fn (ListFilmData $film): bool => ! in_array($film->key(), $existing, true)));
    }

    public function loadDetail(MovieList $list): MovieList
    {
        return $list->load(['user', 'items'])->loadCount('items');
    }
}

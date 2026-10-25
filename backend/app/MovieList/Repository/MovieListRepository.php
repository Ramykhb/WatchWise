<?php

namespace App\MovieList\Repository;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Collection;

class MovieListRepository implements MovieListRepositoryInterface
{
    public function search(MovieListSearchQuery $query, User $viewer): Collection
    {
        $scope = $query->scope;

        return MovieList::with(['user', 'items'])->withCount('items')
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

    public function syncItems(MovieList $list, array $tmdbIds): array
    {
        $existing = $list->items()->pluck('tmdb_id')->all();
        $list->items()->whereNotIn('tmdb_id', $tmdbIds)->delete();

        foreach (array_values($tmdbIds) as $position => $tmdbId) {
            $list->items()->updateOrCreate(['tmdb_id' => $tmdbId], ['position' => $position]);
        }

        return array_values(array_diff($tmdbIds, $existing));
    }

    public function loadDetail(MovieList $list): MovieList
    {
        return $list->load(['user', 'items'])->loadCount('items');
    }
}

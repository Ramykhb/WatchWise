<?php

namespace App\MovieList\Repository;

use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\Support\HiddenMovies;
use App\Support\LikePattern;
use App\Support\MediaType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;

class MovieListRepository implements MovieListRepositoryInterface
{
    public function search(MovieListSearchQuery $query, User $viewer, HiddenMovies $hidden): Collection
    {
        $scope = $query->scope;
        $visibleItems = fn ($items) => $hidden->exclude($items);

        return MovieList::with(['user', 'items' => $visibleItems])->withCount(['items' => $visibleItems])
            ->when($query->contains !== null, fn ($builder) => $builder->withExists([
                'items as has_movie' => fn ($items) => $items->where('tmdb_id', $query->contains)->where('media_type', $query->containsType),
            ]))
            ->when($query->q !== '', fn ($builder) => $builder->whereRaw("title LIKE ? ESCAPE '!'", [LikePattern::contains($query->q)]))
            ->when($scope === 'mine', fn ($builder) => $builder->where('user_id', $viewer->id))
            ->when($scope === 'public', fn ($builder) => $builder->where('is_public', true)->where('user_id', '!=', $viewer->id)
                // Followers-only and private profiles share their public lists with their followers only.
                ->whereHas('user', fn ($owner) => $owner->where(fn ($visible) => $visible
                    ->where('profile_visibility', 'public')
                    ->orWhereHas('followers', fn ($followers) => $followers->whereKey($viewer->id)))))
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

    public function delete(MovieList $list): bool
    {
        return MovieList::query()->whereKey($list->getKey())->delete() > 0;
    }

    public function lockForUpdate(MovieList $list): ?MovieList
    {
        return MovieList::query()->whereKey($list->getKey())->lockForUpdate()->first();
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

    public function loadDetail(MovieList $list, HiddenMovies $hidden): MovieList
    {
        $visibleItems = fn ($items) => $hidden->exclude($items);

        return $list->load(['user', 'items' => $visibleItems])->loadCount(['items' => $visibleItems]);
    }

    public function paginateForStaff(StaffListQuery $query): LengthAwarePaginator
    {
        return MovieList::query()
            ->with('user:id,name,username')
            ->withCount('items')
            ->when($query->search !== '', function (Builder $builder) use ($query): void {
                $pattern = LikePattern::contains($query->search);

                $builder->where(fn (Builder $match) => $match
                    ->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', fn (Builder $owner) => $owner
                        ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->when($query->visibility !== null, fn (Builder $builder) => $builder->where('is_public', $query->visibility === 'public'))
            ->when($query->ownerId !== null, fn (Builder $builder) => $builder->where('user_id', $query->ownerId))
            ->when($query->reported === true, fn (Builder $builder) => $builder->whereHas('reports', fn (Builder $reports) => $reports->open()))
            ->when($query->reported === false, fn (Builder $builder) => $builder->whereDoesntHave('reports', fn (Builder $reports) => $reports->open()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function staffTotals(): array
    {
        $counts = MovieList::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_public = ? then 1 else 0 end) as public_lists', [true])
            ->selectRaw('sum(case when is_public = ? then 1 else 0 end) as private_lists', [false])
            ->toBase()
            ->first();

        return [
            'total' => (int) $counts->total,
            'public' => (int) $counts->public_lists,
            'private' => (int) $counts->private_lists,
            'reported' => MovieList::query()->whereHas('reports', fn (Builder $reports) => $reports->open())->count(),
        ];
    }

    public function loadStaffRow(MovieList $list): MovieList
    {
        return $list->refresh()->load('user:id,name,username')->loadCount('items');
    }

    public function loadStaffDetail(MovieList $list, int $itemLimit): MovieList
    {
        return $list->load(['user:id,name,username', 'items' => fn ($items) => $items->limit($itemLimit)])->loadCount('items');
    }

    public function makePrivate(MovieList $list): bool
    {
        $changed = MovieList::query()->whereKey($list->getKey())->where('is_public', true)->update(['is_public' => false, 'updated_at' => now()]) > 0;
        $list->refresh();

        return $changed;
    }

    public function countOwnedBy(User $user): int
    {
        return $user->movieLists()->count();
    }

    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        return MovieList::query()->where('created_at', '>=', $from)->where('created_at', '<', $until)->count();
    }
}

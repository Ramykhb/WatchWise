<?php

namespace App\MovieList\Repository;

use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\User;
use App\MovieList\DTOs\ListSyncResult;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\Support\HiddenMovies;
use App\Support\LikePattern;
use App\Support\MediaType;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;

class MovieListRepository implements MovieListRepositoryInterface
{
    public function search(MovieListSearchQuery $query, User $viewer, HiddenMovies $hidden): Paginator
    {
        $scope = $query->scope;
        $visibleItems = fn ($items) => $hidden->exclude($items);

        return MovieList::with(['user', 'items' => fn ($items) => $hidden->exclude($items)->limit(5)])->withCount(['items' => $visibleItems])
            ->when($query->contains !== null, fn ($builder) => $builder->withExists([
                'items as has_movie' => fn ($items) => $items->where('tmdb_id', $query->contains)->where('media_type', $query->containsType),
            ]))
            ->when($query->q !== '', fn ($builder) => $builder->whereRaw("title LIKE ? ESCAPE '!'", [LikePattern::contains($query->q)]))
            ->when($scope === 'mine', fn ($builder) => $builder->where('user_id', $viewer->id))
            // Other people's lists follow the profile rule: public list, active owner, profile visible to the
            // viewer (public, or followers-only for a follower; private only for the owner) and "lists" shared.
            ->when($scope === 'public', fn ($builder) => $builder->where('is_public', true)->where('user_id', '!=', $viewer->id)
                ->whereHas('user', fn ($owner) => $this->sharesLists($owner, $viewer)))
            // Public lists by people the user follows, under the same rule.
            ->when($scope === 'following', fn ($builder) => $builder->where('is_public', true)
                ->whereIn('user_id', $viewer->following()->select('users.id'))
                ->whereHas('user', fn ($owner) => $this->sharesLists($owner, $viewer)))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->simplePaginate($query->perPage(), ['*'], 'page', $query->page);
    }

    /** @param Builder<User> $owner */
    private function sharesLists(Builder $owner, User $viewer): void
    {
        $owner->where('is_active', true)
            ->where(fn (Builder $visible) => $visible
                ->where('profile_visibility', 'public')
                ->orWhere(fn (Builder $followersOnly) => $followersOnly->where('profile_visibility', 'followers')
                    ->whereHas('followers', fn (Builder $followers) => $followers->whereKey($viewer->id))))
            ->whereDoesntHave('preferences', fn (Builder $preferences) => $preferences->where('settings->privacy->lists', false));
    }

    public function create(CreateMovieListData $data, int $ownerId): MovieList
    {
        return MovieList::create([
            'user_id' => $ownerId,
            'title' => $data->title,
            'description' => $data->description,
            'is_public' => $data->isPublic,
        ]);
    }

    public function update(MovieList $list, array $attributes): bool
    {
        $list->fill($attributes);
        $changed = $list->isDirty();
        // save() bumps updated_at only when a field changed.
        $list->save();
        if ($changed) {
            $this->bumpRevision($list);
        }

        return $changed;
    }

    public function lock(MovieList $list): MovieList
    {
        return MovieList::query()->lockForUpdate()->findOrFail($list->getKey());
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

        $last = $list->items()->max('position');

        try {
            $list->items()->create([
                'tmdb_id' => $film->tmdbId,
                'media_type' => $film->mediaType,
                'position' => $last === null ? 0 : (int) $last + 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request added the same title between the check and the insert: it's there either way.
            return false;
        }
        $this->bumpRevision($list);

        return true;
    }

    public function removeItem(MovieList $list, ListFilmData $film): bool
    {
        $removed = $list->items()->where('tmdb_id', $film->tmdbId)->where('media_type', $film->mediaType)->delete() > 0;
        if ($removed) {
            $this->bumpRevision($list);
        }

        return $removed;
    }

    public function syncItems(MovieList $list, array $films): ListSyncResult
    {
        $films = array_values($films);
        $wanted = array_map(fn (ListFilmData $film): string => $film->key(), $films);
        $items = $list->items()->orderBy('position')->orderBy('id')->get(['id', 'tmdb_id', 'media_type', 'position']);

        // A movie and a TV show can share a TMDB id, so titles are compared by id and type together.
        $positions = [];
        $stale = [];
        foreach ($items as $item) {
            $key = MediaType::key($item->media_type, $item->tmdb_id);
            $positions[$key] = $item->position;
            if (! in_array($key, $wanted, true)) {
                $stale[] = $item->id;
            }
        }

        if ($stale !== []) {
            $list->items()->whereKey($stale)->delete();
        }

        $added = [];
        $rows = [];
        $addedAt = now();
        foreach ($films as $position => $film) {
            $isNew = ! array_key_exists($film->key(), $positions);
            if ($isNew) {
                $added[] = $film;
            }
            // Titles already at their position are left alone.
            if ($isNew || $positions[$film->key()] !== $position) {
                $rows[] = [
                    'movie_list_id' => $list->id,
                    'tmdb_id' => $film->tmdbId,
                    'media_type' => $film->mediaType,
                    'position' => $position,
                    'added_at' => $addedAt,
                ];
            }
        }

        // Titles that stay only get a new position, so their note and added_at are kept.
        if ($rows !== []) {
            MovieListItem::query()->upsert($rows, ['movie_list_id', 'tmdb_id', 'media_type'], ['position']);
        }

        // PHP turns numeric keys like "550" into ints, so the order is compared as strings.
        $changed = array_map(strval(...), array_keys($positions)) !== $wanted;
        if ($changed) {
            $this->bumpRevision($list);
        }

        return new ListSyncResult($added, $changed);
    }

    private function bumpRevision(MovieList $list): void
    {
        $list->increment('revision', 1, ['updated_at' => now()]);
    }

    public function loadDetail(MovieList $list, HiddenMovies $hidden): MovieList
    {
        $visibleItems = fn ($items) => $hidden->exclude($items);

        return $list->loadMissing('user')->load(['items' => $visibleItems])->loadCount(['items' => $visibleItems]);
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
        $changed = MovieList::query()->whereKey($list->getKey())->where('is_public', true)->increment('revision', 1, ['is_public' => false, 'updated_at' => now()]) > 0;
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

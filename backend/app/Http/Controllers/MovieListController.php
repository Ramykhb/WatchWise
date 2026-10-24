<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\MovieListResource;
use App\Models\MovieList;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** The signed-in user's lists, other members' public lists and the films inside them. */
class MovieListController extends Controller
{
    public function __construct(private readonly ActivityFeedService $activity) {}

    /** GET /api/lists?scope=mine|public|following */
    public function index(Request $request, CurrentUser $current): JsonResponse
    {
        $scope = $request->query('scope', 'mine');
        abort_unless(in_array($scope, ['mine', 'public', 'following'], true), 422, 'Unknown scope.');

        $lists = MovieList::with(['user', 'items'])->withCount('items')
            ->when($scope === 'mine', fn ($q) => $q->where('user_id', $current->id()))
            ->when($scope === 'public', fn ($q) => $q->where('is_public', true)->where('user_id', '!=', $current->id()))
            // Public lists by people the user follows.
            ->when($scope === 'following', fn ($q) => $q->where('is_public', true)
                ->whereIn('user_id', $current->user()->following()->select('users.id')))
            ->latest('updated_at')
            ->limit($scope === 'mine' ? 200 : 30)
            ->get();

        return response()->json(['lists' => MovieListResource::collection($lists)]);
    }

    /** POST /api/lists */
    public function store(Request $request, CurrentUser $current): JsonResponse
    {
        Gate::authorize('create', MovieList::class);
        $data = $this->validated($request);

        $list = DB::transaction(function () use ($data, $current) {
            $list = MovieList::create([
                'user_id' => $current->id(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_public' => $data['is_public'] ?? false,
            ]);
            $this->syncItems($list, $data['tmdb_ids'] ?? []);

            return $list;
        });

        $this->activity->record($current->user(), 'list_created', $list, null, $list->is_public);

        return response()->json(['list' => $this->detail($list)], 201);
    }

    /** GET /api/lists/{list} */
    public function show(MovieList $list): JsonResponse
    {
        Gate::authorize('view', $list);

        return response()->json(['list' => $this->detail($list)]);
    }

    /** PATCH /api/lists/{list} — `tmdb_ids` replaces the films in that order (add, remove, reorder). */
    public function update(Request $request, MovieList $list, CurrentUser $current): JsonResponse
    {
        Gate::authorize('update', $list);
        $data = $this->validated($request, partial: true);

        DB::transaction(function () use ($list, $data, $current) {
            $list->fill(collect($data)->only(['title', 'description', 'is_public'])->all());
            // touch() saves the new fields and bumps updated_at even when only the films changed.
            $list->touch();

            if (array_key_exists('tmdb_ids', $data)) {
                $added = $this->syncItems($list, $data['tmdb_ids']);
                foreach ($added as $tmdbId) {
                    $this->activity->record($current->user(), 'list_movie_added', $list, $tmdbId, $list->is_public);
                }
            }
        });

        return response()->json(['list' => $this->detail($list)]);
    }

    /** DELETE /api/lists/{list} */
    public function destroy(MovieList $list): JsonResponse
    {
        Gate::authorize('delete', $list);
        $list->delete();

        return response()->json(['message' => 'List deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_public' => ['sometimes', 'boolean'],
            'tmdb_ids' => ['sometimes', 'array', 'max:500'],
            'tmdb_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);
    }

    /**
     * Makes the list hold exactly these films in this order, keeping notes of films that stay.
     *
     * @param  array<int>  $tmdbIds
     * @return array<int> films that were not in the list before
     */
    private function syncItems(MovieList $list, array $tmdbIds): array
    {
        $existing = $list->items()->pluck('tmdb_id')->all();
        $list->items()->whereNotIn('tmdb_id', $tmdbIds)->delete();

        foreach (array_values($tmdbIds) as $position => $tmdbId) {
            $list->items()->updateOrCreate(['tmdb_id' => $tmdbId], ['position' => $position]);
        }

        return array_values(array_diff($tmdbIds, $existing));
    }

    private function detail(MovieList $list): MovieListResource
    {
        $list->load(['user', 'items'])->loadCount('items');

        return (new MovieListResource($list))->withItems();
    }
}

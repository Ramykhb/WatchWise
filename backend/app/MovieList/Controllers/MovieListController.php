<?php

namespace App\MovieList\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;
use App\MovieList\FormRequests\MovieListIndexRequest;
use App\MovieList\FormRequests\StoreMovieListRequest;
use App\MovieList\FormRequests\UpdateMovieListRequest;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\MovieList\Resources\MovieListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The signed-in user's lists, other members' public lists and the films inside them. */
class MovieListController
{
    public function __construct(private readonly MovieListServiceInterface $lists) {}

    /** GET /api/lists?scope=mine|public|following&q= — `q` matches the title (Discover › Lists). */
    public function index(MovieListIndexRequest $request, CurrentUser $current): JsonResponse
    {
        $lists = $this->lists->search($request->toDto(), $current->user());

        return response()->json(['lists' => MovieListResource::collection($lists)]);
    }

    /** POST /api/lists */
    public function store(StoreMovieListRequest $request, CurrentUser $current): JsonResponse
    {
        $list = $this->lists->create($request->toDto(), $current->user());

        return response()->json(['list' => $this->present($list)], 201);
    }

    /** GET /api/lists/{list} */
    public function show(MovieList $list): JsonResponse
    {
        Gate::authorize('view', $list);

        return response()->json(['list' => $this->present($this->lists->detail($list))]);
    }

    /** PATCH /api/lists/{list} — `tmdb_ids` replaces the films in that order (add, remove, reorder). */
    public function update(UpdateMovieListRequest $request, MovieList $list): JsonResponse
    {
        $list = $this->lists->update($list, $request->toDto());

        return response()->json(['list' => $this->present($list)]);
    }

    /** DELETE /api/lists/{list} */
    public function destroy(MovieList $list): JsonResponse
    {
        Gate::authorize('delete', $list);
        $this->lists->delete($list);

        return response()->json(['message' => 'List deleted.']);
    }

    private function present(MovieList $list): MovieListResource
    {
        return (new MovieListResource($list))->withItems();
    }
}

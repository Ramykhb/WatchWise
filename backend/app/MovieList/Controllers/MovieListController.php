<?php

namespace App\MovieList\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\FormRequests\AddMovieListItemRequest;
use App\MovieList\FormRequests\MovieListIndexRequest;
use App\MovieList\FormRequests\RemoveMovieListItemRequest;
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

    /**
     * GET /api/lists?scope=mine|public|following&q=&contains=&contains_type= — `q` matches the title
     * (Discover › Lists); `contains` (a TMDB id, a movie unless `contains_type=tv`) adds `has_movie` to
     * each list (the Movie page's "Add to list").
     */
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

    /** POST /api/lists/{list}/items — adds one movie or TV show at the end; adding it twice is harmless. */
    public function addItem(AddMovieListItemRequest $request, MovieList $list): JsonResponse
    {
        return response()->json(['list' => $this->present($this->lists->addMovie($list, $request->film()))]);
    }

    /** DELETE /api/lists/{list}/items/{tmdbId}?type=movie|tv */
    public function removeItem(RemoveMovieListItemRequest $request, MovieList $list, int $tmdbId): JsonResponse
    {
        $film = new ListFilmData($tmdbId, $request->mediaType());

        return response()->json(['list' => $this->present($this->lists->removeMovie($list, $film))]);
    }

    private function present(MovieList $list): MovieListResource
    {
        return (new MovieListResource($list))->withItems();
    }
}

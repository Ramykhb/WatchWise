<?php

namespace App\Tmdb\Controllers;

use App\Http\Controllers\Controller;
use App\Tmdb\FormRequests\MovieDiscoverRequest;
use App\Tmdb\FormRequests\MovieListRequest;
use App\Tmdb\FormRequests\MovieSearchRequest;
use App\Tmdb\InterfaceServices\MovieServiceInterface;
use Illuminate\Http\JsonResponse;

class MovieController extends Controller
{
    public function __construct(private readonly MovieServiceInterface $movies) {}

    /**
     * Fetch one page of a TMDB movie list (popular, top rated, now playing, upcoming).
     */
    public function index(MovieListRequest $request): JsonResponse
    {
        return response()->json($this->movies->list($request->toDto()));
    }

    /**
     * Search TMDB movies by title.
     */
    public function search(MovieSearchRequest $request): JsonResponse
    {
        return response()->json($this->movies->search($request->toDto()));
    }

    /**
     * Browse TMDB movies with Discover's filters.
     */
    public function discover(MovieDiscoverRequest $request): JsonResponse
    {
        return response()->json($this->movies->discover($request->toDto()));
    }

    /**
     * One movie with its cast, directors, trailer, certification and related movies.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json($this->movies->find($id));
    }
}

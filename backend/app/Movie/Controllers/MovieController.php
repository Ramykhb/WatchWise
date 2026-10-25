<?php

namespace App\Movie\Controllers;

use App\Movie\FormRequests\MovieDiscoverRequest;
use App\Movie\FormRequests\MovieListRequest;
use App\Movie\FormRequests\MovieSearchRequest;
use App\Movie\InterfaceServices\MovieServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController
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
     * One movie (or, with ?type=tv, one TV show) with its cast, directors, trailer, certification and related titles.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type') === 'tv' ? 'tv' : 'movie';

        return response()->json($this->movies->find($id, $type));
    }
}

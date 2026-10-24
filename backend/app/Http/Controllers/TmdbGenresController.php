<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TmdbGenresController extends Controller
{
    /**
     * List TMDB's movie genres (id and name), cached for a day.
     */
    public function __invoke(TmdbService $tmdb): JsonResponse
    {
        try {
            $genres = Cache::remember('tmdb.movie_genres', now()->addDay(), fn (): array => array_map(
                fn (array $genre): array => ['id' => $genre['id'], 'name' => $genre['name']],
                $tmdb->get('/genre/movie/list')->json('genres', []),
            ));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $tmdb->unavailableResponse();
        }

        return response()->json(['genres' => $genres]);
    }
}

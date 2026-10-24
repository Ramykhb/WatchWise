<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TmdbMovieSearchController extends Controller
{
    /**
     * Search TMDB movies by title.
     */
    public function __invoke(Request $request, TmdbService $tmdb): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'between:1,500'],
            'year' => ['sometimes', 'integer', 'between:1874,2100'],
        ]);

        try {
            $response = $tmdb->get('/search/movie', array_filter([
                'query' => $validated['q'],
                'page' => (int) ($validated['page'] ?? 1),
                'primary_release_year' => $validated['year'] ?? null,
                'include_adult' => 'false',
            ], fn ($value): bool => $value !== null));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $tmdb->unavailableResponse();
        }

        return response()->json(['query' => $validated['q'], ...$tmdb->formatPage($response)]);
    }
}

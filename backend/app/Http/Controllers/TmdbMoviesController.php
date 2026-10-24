<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class TmdbMoviesController extends Controller
{
    public const LISTS = ['popular', 'top_rated', 'now_playing', 'upcoming'];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list' => ['sometimes', 'string', Rule::in(self::LISTS)],
            'page' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $list = $validated['list'] ?? 'popular';
        $page = (int) ($validated['page'] ?? 1);

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(config('services.tmdb.url')."/movie/{$list}", [
                    'api_key' => config('services.tmdb.key'),
                    'language' => 'en-US',
                    'page' => $page,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Movie service is temporarily unavailable.',
            ], 502);
        }

        return response()->json([
            'list' => $list,
            'page' => $response->json('page'),
            'total_pages' => $response->json('total_pages'),
            'total_results' => $response->json('total_results'),
            'movies' => array_map($this->formatMovie(...), $response->json('results', [])),
        ]);
    }

    private function formatMovie(array $movie): array
    {
        $imageUrl = config('services.tmdb.image_url');
        $releaseDate = ($movie['release_date'] ?? null) ?: null;

        return [
            'id' => $movie['id'],
            'title' => $movie['title'] ?? '',
            'overview' => $movie['overview'] ?? '',
            'release_date' => $releaseDate,
            'year' => $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            'rating' => round(($movie['vote_average'] ?? 0) / 2, 1),
            'vote_count' => $movie['vote_count'] ?? 0,
            'genre_ids' => $movie['genre_ids'] ?? [],
            'poster_url' => isset($movie['poster_path']) ? "{$imageUrl}/w500{$movie['poster_path']}" : null,
            'backdrop_url' => isset($movie['backdrop_path']) ? "{$imageUrl}/w1280{$movie['backdrop_path']}" : null,
        ];
    }
}
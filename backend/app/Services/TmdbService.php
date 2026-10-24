<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    /**
     * Call a TMDB endpoint with the API key and return the response.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function get(string $path, array $query = []): Response
    {
        return Http::acceptJson()
            ->timeout(10)
            ->get(config('services.tmdb.url').$path, [
                'api_key' => config('services.tmdb.key'),
                'language' => 'en-US',
                ...$query,
            ])
            ->throw();
    }

    /**
     * Turn a TMDB page of movies into the shape every movie list endpoint returns.
     *
     * @return array{page: int, total_pages: int, total_results: int, movies: list<array<string, mixed>>}
     */
    public function formatPage(Response $response): array
    {
        return [
            'page' => (int) $response->json('page', 1),
            // TMDB never serves more than 500 pages, even when it reports more.
            'total_pages' => min((int) $response->json('total_pages', 0), 500),
            'total_results' => (int) $response->json('total_results', 0),
            'movies' => array_map($this->formatMovie(...), $response->json('results', [])),
        ];
    }

    /**
     * Keep only the movie fields the frontend needs and build full image URLs.
     *
     * @param  array<string, mixed>  $movie
     * @return array{id: int, title: string, overview: string, release_date: ?string, year: ?int, rating: float, vote_count: int, genre_ids: list<int>, poster_url: ?string, backdrop_url: ?string}
     */
    public function formatMovie(array $movie): array
    {
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
            'poster_url' => $this->imageUrl($movie['poster_path'] ?? null, 'w500'),
            'backdrop_url' => $this->imageUrl($movie['backdrop_path'] ?? null, 'w1280'),
        ];
    }

    /**
     * Turn a TMDB image path into a full URL at the given size (w185, w500, w1280…).
     */
    public function imageUrl(?string $path, string $size): ?string
    {
        return $path ? config('services.tmdb.image_url')."/{$size}{$path}" : null;
    }

    /**
     * The JSON error every TMDB endpoint returns when TMDB can't be reached.
     */
    public function unavailableResponse(): JsonResponse
    {
        return response()->json(['message' => 'Movie service is temporarily unavailable.'], 502);
    }
}

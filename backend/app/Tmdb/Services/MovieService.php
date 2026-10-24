<?php

namespace App\Tmdb\Services;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\DTOs\Responses\MoviePageResponse;
use App\Tmdb\DTOs\Responses\MovieResponse;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use App\Tmdb\InterfaceServices\MovieServiceInterface;

class MovieService implements MovieServiceInterface
{
    public function __construct(private readonly MovieRepositoryInterface $movies) {}

    public function list(MovieListQuery $query): MoviePageResponse
    {
        return $this->toPage($this->movies->list($query), ['list' => $query->list]);
    }

    public function search(MovieSearchQuery $query): MoviePageResponse
    {
        return $this->toPage($this->movies->search($query), ['query' => $query->query]);
    }

    public function discover(MovieDiscoverQuery $query): MoviePageResponse
    {
        return $this->toPage($this->movies->discover($query), ['sort' => $query->sort]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $meta
     */
    private function toPage(array $payload, array $meta): MoviePageResponse
    {
        return new MoviePageResponse(
            page: (int) ($payload['page'] ?? 1),
            // TMDB never serves more than 500 pages, even when it reports more.
            totalPages: min((int) ($payload['total_pages'] ?? 0), 500),
            totalResults: (int) ($payload['total_results'] ?? 0),
            movies: array_values(array_map($this->toMovie(...), $payload['results'] ?? [])),
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $movie
     */
    private function toMovie(array $movie): MovieResponse
    {
        $releaseDate = ($movie['release_date'] ?? null) ?: null;

        return new MovieResponse(
            id: $movie['id'],
            title: $movie['title'] ?? '',
            overview: $movie['overview'] ?? '',
            releaseDate: $releaseDate,
            year: $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            rating: round(($movie['vote_average'] ?? 0) / 2, 1),
            voteCount: $movie['vote_count'] ?? 0,
            genreIds: $movie['genre_ids'] ?? [],
            posterUrl: $this->imageUrl($movie['poster_path'] ?? null, 'w500'),
            backdropUrl: $this->imageUrl($movie['backdrop_path'] ?? null, 'w1280'),
        );
    }

    private function imageUrl(?string $path, string $size): ?string
    {
        return $path ? config('services.tmdb.image_url')."/{$size}{$path}" : null;
    }
}

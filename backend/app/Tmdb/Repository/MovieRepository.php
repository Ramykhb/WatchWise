<?php

namespace App\Tmdb\Repository;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\Exceptions\MovieNotFound;
use App\Tmdb\Exceptions\TmdbNotFound;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use App\Tmdb\Support\TmdbHttpClient;

class MovieRepository implements MovieRepositoryInterface
{
    /**
     * Discover's sort options and the TMDB sort each one uses.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'popularity' => 'popularity.desc',
        'rating' => 'vote_average.desc',
        'release_date' => 'primary_release_date.desc',
        'title' => 'title.asc',
    ];

    public function __construct(private readonly TmdbHttpClient $client) {}

    public function list(MovieListQuery $query): array
    {
        return $this->client->get("/movie/{$query->list}", ['page' => $query->page])->json();
    }

    public function search(MovieSearchQuery $query): array
    {
        return $this->client->get('/search/movie', array_filter([
            'query' => $query->query,
            'page' => $query->page,
            'primary_release_year' => $query->year,
            'include_adult' => 'false',
        ], fn ($value): bool => $value !== null))->json();
    }

    public function discover(MovieDiscoverQuery $query): array
    {
        return $this->client->get('/discover/movie', array_filter([
            'with_genres' => $query->genres,
            'primary_release_date.gte' => $query->yearFrom !== null ? "{$query->yearFrom}-01-01" : null,
            'primary_release_date.lte' => $query->yearTo !== null ? "{$query->yearTo}-12-31" : null,
            'vote_average.gte' => $query->minRating !== null ? $query->minRating * 2 : null,
            'with_runtime.gte' => $query->runtimeMin,
            'with_runtime.lte' => $query->runtimeMax,
            'with_original_language' => $query->language,
            // Without a vote floor, "Rating" and "Title" are topped by obscure films with a handful of votes.
            'vote_count.gte' => in_array($query->sort, ['rating', 'title'], true) ? 100 : null,
            'sort_by' => self::SORTS[$query->sort] ?? self::SORTS['popularity'],
            'page' => $query->page,
            'include_adult' => 'false',
        ], fn ($value): bool => $value !== null))->json();
    }

    public function find(int $id): array
    {
        try {
            // One call: append_to_response bundles the extra data with the movie.
            return $this->client->get("/movie/{$id}", [
                'append_to_response' => 'credits,videos,recommendations,similar,release_dates',
            ])->json();
        } catch (TmdbNotFound) {
            throw new MovieNotFound($id);
        }
    }
}

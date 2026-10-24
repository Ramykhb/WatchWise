<?php

namespace App\Tmdb\Services;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\DTOs\Responses\CastMemberResponse;
use App\Tmdb\DTOs\Responses\GenreResponse;
use App\Tmdb\DTOs\Responses\MovieDetailsResponse;
use App\Tmdb\DTOs\Responses\MoviePageResponse;
use App\Tmdb\DTOs\Responses\MovieResponse;
use App\Tmdb\DTOs\Responses\PersonResponse;
use App\Tmdb\DTOs\Responses\TrailerResponse;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use App\Tmdb\InterfaceServices\MovieServiceInterface;
use Illuminate\Support\Facades\Cache;

class MovieService implements MovieServiceInterface
{
    /**
     * How many cast members and related movies a movie's details include.
     */
    private const CAST_LIMIT = 12;

    private const SIMILAR_LIMIT = 10;

    private const DETAILS_CACHE_PREFIX = 'tmdb.movie.';

    private const DETAILS_CACHE_HOURS = 6;

    public function __construct(private readonly MovieRepositoryInterface $movies) {}

    public function list(MovieListQuery $query): MoviePageResponse
    {
        return $this->toPage($this->movies->list($query), ['list' => $query->list]);
    }

    public function search(MovieSearchQuery $query): MoviePageResponse
    {
        $meta = ['query' => $query->query];

        return match ($query->type) {
            'tv' => $this->toPage($this->movies->searchTv($query), $meta, 'tv'),
            // TMDB gives no relevance score to merge on, so a combined search is ordered by popularity.
            'all' => $this->mergedPage([
                'movie' => $this->movies->search($query),
                'tv' => $this->movies->searchTv($query),
            ], 'popularity', $meta),
            default => $this->toPage($this->movies->search($query), $meta),
        };
    }

    public function discover(MovieDiscoverQuery $query): MoviePageResponse
    {
        $meta = ['sort' => $query->sort];

        return match ($query->type) {
            'tv' => $this->toPage($this->movies->discoverTv($query), $meta, 'tv'),
            'all' => $this->mergedPage([
                'movie' => $this->movies->discover($query),
                'tv' => $this->movies->discoverTv($query),
            ], $query->sort, $meta),
            default => $this->toPage($this->movies->discover($query), $meta),
        };
    }

    public function find(int $id, string $type = 'movie'): MovieDetailsResponse
    {
        // A movie's details rarely change, so repeat visits don't spend the TMDB quota.
        // Only successful answers are cached: MovieNotFound and TmdbUnavailable pass straight through.
        $movie = Cache::remember(self::DETAILS_CACHE_PREFIX.($type === 'tv' ? 'tv.' : '').$id, now()->addHours(self::DETAILS_CACHE_HOURS), fn () => $this->movies->find($id, $type));

        // TMDB's recommendations match far better than its "similar" list, which is only the fallback.
        $related = ($movie['recommendations']['results'] ?? []) ?: ($movie['similar']['results'] ?? []);

        return new MovieDetailsResponse(
            movie: $this->toMovie($movie, $type),
            genres: array_values(array_map(
                fn (array $genre): GenreResponse => new GenreResponse((int) $genre['id'], (string) $genre['name']),
                $movie['genres'] ?? [],
            )),
            tagline: ($movie['tagline'] ?? null) ?: null,
            // A tv show has an episode length instead of a runtime.
            runtime: ($movie['runtime'] ?? $movie['episode_run_time'][0] ?? null) ?: null,
            status: $movie['status'] ?? null,
            tmdbScore: round($movie['vote_average'] ?? 0, 1),
            originalLanguage: $this->originalLanguage($movie),
            certification: $type === 'tv' ? $this->tvCertification($movie) : $this->certification($movie),
            budget: ($movie['budget'] ?? 0) ?: null,
            revenue: ($movie['revenue'] ?? 0) ?: null,
            // A tv show has creators rather than directors.
            directors: $type === 'tv'
                ? collect($movie['created_by'] ?? [])
                    ->map(fn (array $person): PersonResponse => new PersonResponse($person['id'], $person['name']))
                    ->values()
                    ->all()
                : collect($movie['credits']['crew'] ?? [])
                    ->where('job', 'Director')
                    ->map(fn (array $person): PersonResponse => new PersonResponse($person['id'], $person['name']))
                    ->values()
                    ->all(),
            cast: collect($movie['credits']['cast'] ?? [])
                ->take(self::CAST_LIMIT)
                ->map(fn (array $person): CastMemberResponse => new CastMemberResponse(
                    id: $person['id'],
                    name: $person['name'],
                    character: $person['character'] ?? '',
                    profileUrl: $this->imageUrl($person['profile_path'] ?? null, 'w185'),
                ))
                ->values()
                ->all(),
            trailer: $this->trailer($movie),
            similar: array_values(array_map(fn (array $item): MovieResponse => $this->toMovie($item, $type), array_slice($related, 0, self::SIMILAR_LIMIT))),
        );
    }

    /**
     * The US age rating of a tv show (e.g. "TV-MA"), or null when TMDB has none.
     *
     * @param  array<string, mixed>  $show
     */
    private function tvCertification(array $show): ?string
    {
        $rating = collect($show['content_ratings']['results'] ?? [])->firstWhere('iso_3166_1', 'US');

        return ($rating['rating'] ?? null) ?: null;
    }

    /**
     * The original language's code and English name (e.g. "en" and "English").
     *
     * @param  array<string, mixed>  $movie
     * @return array{code: string, name: string}|null
     */
    private function originalLanguage(array $movie): ?array
    {
        $code = $movie['original_language'] ?? null;

        if (! $code) {
            return null;
        }

        $spoken = collect($movie['spoken_languages'] ?? [])->firstWhere('iso_639_1', $code);

        return ['code' => $code, 'name' => ($spoken['english_name'] ?? null) ?: strtoupper($code)];
    }

    /**
     * The US age rating (e.g. "PG-13"), or null when TMDB has none.
     *
     * @param  array<string, mixed>  $movie
     */
    private function certification(array $movie): ?string
    {
        $unitedStates = collect($movie['release_dates']['results'] ?? [])->firstWhere('iso_3166_1', 'US');

        return collect($unitedStates['release_dates'] ?? [])
            ->pluck('certification')
            ->first(fn (?string $certification): bool => filled($certification));
    }

    /**
     * The best YouTube trailer: an official trailer first, then any trailer, then a teaser.
     *
     * @param  array<string, mixed>  $movie
     */
    private function trailer(array $movie): ?TrailerResponse
    {
        $videos = collect($movie['videos']['results'] ?? [])->where('site', 'YouTube');

        $video = $videos->first(fn (array $video): bool => $video['type'] === 'Trailer' && ($video['official'] ?? false))
            ?? $videos->firstWhere('type', 'Trailer')
            ?? $videos->firstWhere('type', 'Teaser');

        return $video ? new TrailerResponse($video['name'] ?? 'Trailer', $video['key']) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $meta
     */
    private function toPage(array $payload, array $meta, string $type = 'movie'): MoviePageResponse
    {
        return new MoviePageResponse(
            page: (int) ($payload['page'] ?? 1),
            // TMDB never serves more than 500 pages, even when it reports more.
            totalPages: min((int) ($payload['total_pages'] ?? 0), 500),
            totalResults: (int) ($payload['total_results'] ?? 0),
            movies: array_values(array_map(fn (array $item): MovieResponse => $this->toMovie($item, $type), $payload['results'] ?? [])),
            meta: $meta,
        );
    }

    /**
     * One page holding the same page of movies and tv shows, ordered by the chosen sort.
     *
     * @param  array<string, array<string, mixed>>  $payloads  TMDB's page payload for each type
     * @param  array<string, string>  $meta
     */
    private function mergedPage(array $payloads, string $sort, array $meta): MoviePageResponse
    {
        $items = [];

        foreach ($payloads as $type => $payload) {
            foreach ($payload['results'] ?? [] as $item) {
                $items[] = ['type' => $type, 'item' => $item];
            }
        }

        usort($items, fn (array $a, array $b): int => $this->compareForSort($a['item'], $b['item'], $sort));

        return new MoviePageResponse(
            page: (int) max(array_map(fn (array $payload): int => (int) ($payload['page'] ?? 1), $payloads)),
            totalPages: min((int) max(array_map(fn (array $payload): int => (int) ($payload['total_pages'] ?? 0), $payloads)), 500),
            totalResults: (int) array_sum(array_map(fn (array $payload): int => (int) ($payload['total_results'] ?? 0), $payloads)),
            movies: array_map(fn (array $entry): MovieResponse => $this->toMovie($entry['item'], $entry['type']), $items),
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareForSort(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'rating' => ($b['vote_average'] ?? 0) <=> ($a['vote_average'] ?? 0),
            'release_date' => ($b['release_date'] ?? $b['first_air_date'] ?? '') <=> ($a['release_date'] ?? $a['first_air_date'] ?? ''),
            'title' => strcasecmp($a['title'] ?? $a['name'] ?? '', $b['title'] ?? $b['name'] ?? ''),
            default => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0),
        };
    }

    /**
     * @param  array<string, mixed>  $movie
     */
    private function toMovie(array $movie, string $type = 'movie'): MovieResponse
    {
        // Tv shows come with a name and first air date instead of a title and release date.
        $releaseDate = ($movie['release_date'] ?? $movie['first_air_date'] ?? null) ?: null;

        return new MovieResponse(
            id: $movie['id'],
            title: $movie['title'] ?? $movie['name'] ?? '',
            overview: $movie['overview'] ?? '',
            releaseDate: $releaseDate,
            year: $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            rating: round(($movie['vote_average'] ?? 0) / 2, 1),
            voteCount: $movie['vote_count'] ?? 0,
            genreIds: $movie['genre_ids'] ?? [],
            posterUrl: $this->imageUrl($movie['poster_path'] ?? null, 'w500'),
            backdropUrl: $this->imageUrl($movie['backdrop_path'] ?? null, 'w1280'),
            mediaType: $type,
        );
    }

    private function imageUrl(?string $path, string $size): ?string
    {
        return $path ? config('services.tmdb.image_url')."/{$size}{$path}" : null;
    }
}

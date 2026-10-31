<?php

namespace App\Movie\Services;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Movie\DTOs\Requests\MovieDiscoverQuery;
use App\Movie\DTOs\Requests\MovieListQuery;
use App\Movie\DTOs\Requests\MovieSearchQuery;
use App\Movie\DTOs\Requests\MovieTrendingQuery;
use App\Movie\DTOs\Responses\CastMemberResponse;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\DTOs\Responses\PersonResponse;
use App\Movie\DTOs\Responses\TrailerResponse;
use App\Movie\InterfaceRepository\MovieRepositoryInterface;
use App\Movie\InterfaceServices\MovieServiceInterface;
use Closure;
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

    /**
     * A combined movies + tv page holds as many titles as one TMDB page, and goes this many pages deep.
     */
    private const MERGED_PAGE_SIZE = 20;

    private const MERGED_MAX_PAGES = 10;

    public function __construct(private readonly MovieRepositoryInterface $movies) {}

    public function trending(MovieTrendingQuery $query): MoviePageResponse
    {
        $payload = Cache::remember('tmdb.trending.week.'.$query->type, now()->addHour(),
            fn (): array => $this->movies->trending($query));

        return $this->toPage($payload, ['time_window' => 'week'], $query->type);
    }

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
            'all' => $this->mergedPage(
                fn (int $page): array => $this->movies->search($query->withPage($page)),
                fn (int $page): array => $this->movies->searchTv($query->withPage($page)),
                $query->page,
                'popularity',
                $meta,
            ),
            default => $this->toPage($this->movies->search($query), $meta),
        };
    }

    public function discover(MovieDiscoverQuery $query): MoviePageResponse
    {
        $meta = ['sort' => $query->sort];

        return match ($query->type) {
            'tv' => $this->toPage($this->movies->discoverTv($query), $meta, 'tv'),
            'all' => $this->mergedPage(
                fn (int $page): array => $this->movies->discover($query->withPage($page)),
                fn (int $page): array => $this->movies->discoverTv($query->withPage($page)),
                $query->page,
                $query->sort,
                $meta,
            ),
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
     * One page of movies and tv shows ordered together by the chosen sort.
     *
     * Each source is already sorted by TMDB, so the combined page N is the slice at offset (N - 1) * 20 of the
     * two lists merged. That needs the first N pages of each source, which is why combined results stop at
     * MERGED_MAX_PAGES: deeper pages would cost more and more TMDB calls.
     *
     * @param  Closure(int): array<string, mixed>  $fetchMovies  TMDB's page payload of movies, by page number
     * @param  Closure(int): array<string, mixed>  $fetchShows  TMDB's page payload of tv shows, by page number
     * @param  array<string, string>  $meta
     */
    private function mergedPage(Closure $fetchMovies, Closure $fetchShows, int $page, string $sort, array $meta): MoviePageResponse
    {
        $page = min($page, self::MERGED_MAX_PAGES);
        $items = [];
        $totalResults = 0;

        foreach (['movie' => $fetchMovies, 'tv' => $fetchShows] as $type => $fetch) {
            for ($number = 1; $number <= $page; $number++) {
                $payload = $fetch($number);

                foreach ($payload['results'] ?? [] as $item) {
                    $items[] = ['type' => $type, 'item' => $item];
                }

                if ($number === 1) {
                    $totalResults += (int) ($payload['total_results'] ?? 0);
                }

                if ($number >= (int) ($payload['total_pages'] ?? 0)) {
                    break;
                }
            }
        }

        usort($items, fn (array $a, array $b): int => $this->compareForSort($a['item'], $b['item'], $sort));

        return new MoviePageResponse(
            page: $page,
            totalPages: min((int) ceil($totalResults / self::MERGED_PAGE_SIZE), self::MERGED_MAX_PAGES),
            totalResults: $totalResults,
            movies: array_map(
                fn (array $entry): MovieResponse => $this->toMovie($entry['item'], $entry['type']),
                array_slice($items, ($page - 1) * self::MERGED_PAGE_SIZE, self::MERGED_PAGE_SIZE),
            ),
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

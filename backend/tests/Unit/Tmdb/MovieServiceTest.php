<?php

namespace Tests\Unit\Tmdb;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use App\Tmdb\Services\MovieService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MovieServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function service(string $method, array $payload): MovieService
    {
        /** @var MovieRepositoryInterface&MockInterface $repository */
        $repository = Mockery::mock(MovieRepositoryInterface::class);
        $repository->shouldReceive($method)->once()->andReturn($payload);

        return new MovieService($repository);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'page' => 2,
            'total_pages' => 9000,
            'total_results' => 180000,
            'results' => [
                [
                    'id' => 7,
                    'title' => 'Dune',
                    'overview' => 'Sand.',
                    'release_date' => '2021-10-22',
                    'vote_average' => 7.84,
                    'vote_count' => 100,
                    'genre_ids' => [12, 878],
                    'poster_path' => '/p.jpg',
                    'backdrop_path' => '/b.jpg',
                ],
                ['id' => 8, 'release_date' => ''],
            ],
        ];
    }

    public function test_list_maps_the_payload_and_echoes_the_list(): void
    {
        config(['services.tmdb.image_url' => 'https://img.test']);

        $json = $this->service('list', $this->payload())
            ->list(new MovieListQuery('top_rated', 2))
            ->jsonSerialize();

        $this->assertSame('top_rated', $json['list']);
        $this->assertSame(['list', 'page', 'total_pages', 'total_results', 'movies'], array_keys($json));
        $this->assertSame(2, $json['page']);
        $this->assertSame(500, $json['total_pages']);
        $this->assertSame(180000, $json['total_results']);

        $this->assertSame([
            'id' => 7,
            'media_type' => 'movie',
            'title' => 'Dune',
            'overview' => 'Sand.',
            'release_date' => '2021-10-22',
            'year' => 2021,
            'rating' => 3.9,
            'vote_count' => 100,
            'genre_ids' => [12, 878],
            'poster_url' => 'https://img.test/w500/p.jpg',
            'backdrop_url' => 'https://img.test/w1280/b.jpg',
        ], $json['movies'][0]->jsonSerialize());
    }

    public function test_missing_fields_get_defaults(): void
    {
        $movie = $this->service('list', $this->payload())
            ->list(new MovieListQuery)
            ->jsonSerialize()['movies'][1]
            ->jsonSerialize();

        $this->assertSame('', $movie['title']);
        $this->assertSame('', $movie['overview']);
        $this->assertNull($movie['release_date']);
        $this->assertNull($movie['year']);
        $this->assertSame(0.0, $movie['rating']);
        $this->assertSame(0, $movie['vote_count']);
        $this->assertSame([], $movie['genre_ids']);
        $this->assertNull($movie['poster_url']);
        $this->assertNull($movie['backdrop_url']);
    }

    public function test_search_echoes_the_query(): void
    {
        $json = $this->service('search', $this->payload())
            ->search(new MovieSearchQuery('dune'))
            ->jsonSerialize();

        $this->assertSame('query', array_key_first($json));
        $this->assertSame('dune', $json['query']);
    }

    public function test_discover_echoes_the_sort(): void
    {
        $json = $this->service('discover', $this->payload())
            ->discover(new MovieDiscoverQuery(sort: 'rating'))
            ->jsonSerialize();

        $this->assertSame('sort', array_key_first($json));
        $this->assertSame('rating', $json['sort']);
    }
}

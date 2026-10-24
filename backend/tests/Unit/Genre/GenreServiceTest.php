<?php

namespace Tests\Unit\Genre;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Support\Exceptions\TmdbUnavailable;
use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Genre\Services\GenreService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GenreServiceTest extends TestCase
{
    private MockInterface $repository;

    private GenreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->repository = Mockery::mock(GenreRepositoryInterface::class);
        $this->service = new GenreService($this->repository);
    }

    public function test_it_maps_repository_genres_to_responses(): void
    {
        $this->repository->shouldReceive('all')->once()->andReturn([
            ['id' => 28, 'name' => 'Action'],
            ['id' => 35, 'name' => 'Comedy'],
        ]);

        $genres = $this->service->all();

        $this->assertEquals([new GenreResponse(28, 'Action'), new GenreResponse(35, 'Comedy')], $genres);
    }

    public function test_it_calls_the_repository_once_thanks_to_the_cache(): void
    {
        $this->repository->shouldReceive('all')->once()->andReturn([['id' => 28, 'name' => 'Action']]);

        $this->service->all();
        $second = $this->service->all();

        $this->assertEquals([new GenreResponse(28, 'Action')], $second);
    }

    public function test_it_propagates_unavailability_and_caches_nothing(): void
    {
        $this->repository->shouldReceive('all')->once()->andThrow(new TmdbUnavailable);

        try {
            $this->service->all();
            $this->fail('Expected TmdbUnavailable.');
        } catch (TmdbUnavailable) {
            $this->assertFalse(Cache::has('tmdb.movie_genres'));
        }
    }
}

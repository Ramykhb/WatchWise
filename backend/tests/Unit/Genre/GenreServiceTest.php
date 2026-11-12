<?php

namespace Tests\Unit\Genre;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceRepository\GenreOverrideRepositoryInterface;
use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Genre\Services\GenreService;
use App\Models\GenreOverride;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GenreServiceTest extends TestCase
{
    private MockInterface $repository;

    private MockInterface $overrides;

    private GenreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->repository = Mockery::mock(GenreRepositoryInterface::class);
        $this->overrides = Mockery::mock(GenreOverrideRepositoryInterface::class);
        $this->overrides->shouldReceive('forType')->andReturn(collect());
        $this->service = new GenreService($this->repository, $this->overrides);
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

    public function test_the_raw_tmdb_list_is_cached_and_ignores_overrides(): void
    {
        $this->repository->shouldReceive('all')->once()->andReturn([['id' => 28, 'name' => 'Action']]);

        $this->assertEquals([new GenreResponse(28, 'Action')], $this->service->tmdb());
        $this->assertEquals([new GenreResponse(28, 'Action')], $this->service->tmdb());
    }

    public function test_saving_default_values_removes_the_override_instead_of_storing_it(): void
    {
        $existing = new GenreOverride(['type' => 'movie', 'tmdb_id' => 28, 'name' => 'Fights']);
        $this->overrides->shouldReceive('find')->with('movie', 28)->andReturn($existing);
        $this->overrides->shouldReceive('delete')->once()->with($existing);
        $this->overrides->shouldNotReceive('save');

        $saved = $this->service->saveOverride('movie', 28, ['name' => null, 'is_hidden' => false, 'position' => null], 1);

        $this->assertNull($saved);
    }
}

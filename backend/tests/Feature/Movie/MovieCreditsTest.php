<?php

namespace Tests\Feature\Movie;

use App\Movie\InterfaceRepository\MovieRepositoryInterface;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieCreditsTest extends TestCase
{
    public function test_metadata_reuses_details_then_fetches_missing_titles_with_credits_in_one_pool(): void
    {
        $payload = ['genres' => [['id' => 18, 'name' => 'Drama']], 'credits' => $this->credits()];
        Cache::put('tmdb.movie.42', $payload, 3600);
        Http::fake(['https://api.themoviedb.org/3/tv/42*' => Http::response($payload)]);
        Http::preventStrayRequests();
        $service = app(MovieServiceInterface::class);
        $titles = [['id' => 42, 'media_type' => 'movie'], ['id' => 42, 'media_type' => 'tv']];

        $metadata = $service->metadata($titles);
        $this->assertSame($payload, $metadata['42']);
        $this->assertSame($payload, $metadata['tv-42']);
        $this->assertSame($metadata, $service->metadata($titles));
        $this->assertCount(20, $service->credits($titles)['tv-42']['cast']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['append_to_response'] === 'credits');
        $this->assertNull(Cache::get('tmdb.movie.tv.42'));
    }

    private function credits(): array
    {
        return ['cast' => array_map(fn (int $id): array => ['id' => $id, 'name' => "Actor {$id}"], range(1, 20)),
            'crew' => [['id' => 30, 'name' => 'Director', 'job' => 'Director']]];
    }

    public function test_it_reuses_full_cached_details_and_keeps_movie_and_tv_ids_separate(): void
    {
        Cache::put('tmdb.movie.42', ['credits' => $this->credits()], 3600);
        Cache::put('tmdb.movie.tv.42', ['credits' => ['cast' => [], 'crew' => []]], 3600);
        Http::preventStrayRequests();

        $result = app(MovieServiceInterface::class)->credits([
            ['id' => 42, 'media_type' => 'movie'], ['id' => 42, 'media_type' => 'tv'],
        ]);

        $this->assertCount(20, $result['42']['cast']);
        $this->assertSame(['cast' => [], 'crew' => []], $result['tv-42']);
        Http::assertNothingSent();
    }

    public function test_only_missing_credits_are_fetched_and_reused_without_poisoning_details_cache(): void
    {
        Cache::put('tmdb.movie.42', ['credits' => $this->credits()], 3600);
        Http::fake(['https://api.themoviedb.org/3/tv/42/credits*' => Http::response($this->credits())]);
        Http::preventStrayRequests();
        $titles = [['id' => 42, 'media_type' => 'movie'], ['id' => 42, 'media_type' => 'tv']];
        $service = app(MovieServiceInterface::class);

        $first = $service->credits($titles);
        $this->assertSame($first, $service->credits($titles));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/tv/42/credits?')
            && $request['language'] === 'en-US' && $request['api_key'] === config('services.tmdb.key')
            && ! isset($request['append_to_response']));
        $this->assertNull(Cache::get('tmdb.movie.tv.42'));
        $this->assertSame($this->credits(), Cache::get('tmdb.credits.tv-42'));

        $this->travel(361)->minutes();
        $service->credits([['id' => 42, 'media_type' => 'tv']]);
        Http::assertSentCount(2);
    }

    public function test_large_histories_fetch_every_title_in_bounded_batches_and_deduplicate_inputs(): void
    {
        $titles = array_map(fn (int $id): array => ['id' => $id, 'media_type' => 'movie'], range(1, 103));
        $repository = $this->mock(MovieRepositoryInterface::class);
        $batches = [];
        $repository->shouldNotReceive('find');
        $repository->shouldReceive('credits')->times(21)->andReturnUsing(function (array $batch) use (&$batches): array {
            $this->assertLessThanOrEqual(5, count($batch));
            $batches = [...$batches, ...$batch];

            return collect($batch)->mapWithKeys(fn (array $title): array => [$title['id'] => $this->credits()])->all();
        });

        $result = app(MovieServiceInterface::class)->credits([...$titles, $titles[0]]);

        $this->assertCount(103, $result);
        $this->assertSame($titles, $batches);
        $this->assertCount(20, $result['103']['cast']);
    }

    public function test_removed_titles_do_not_prevent_other_credits_from_being_counted(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/movie/42/credits*' => Http::response([], 404),
            'https://api.themoviedb.org/3/tv/42/credits*' => Http::response($this->credits()),
        ]);
        $result = app(MovieServiceInterface::class)->credits([
            ['id' => 42, 'media_type' => 'movie'], ['id' => 42, 'media_type' => 'tv'],
        ]);

        $this->assertArrayNotHasKey('42', $result);
        $this->assertSame($this->credits(), $result['tv-42']);
    }

    public function test_http_failures_are_not_cached_as_empty_credits(): void
    {
        Http::fakeSequence()->push([], 500)->push($this->credits());
        $service = app(MovieServiceInterface::class);
        $titles = [['id' => 42, 'media_type' => 'movie']];
        try {
            $service->credits($titles);
            $this->fail('Expected TMDB failure.');
        } catch (TmdbUnavailable) {
            $this->assertNull(Cache::get('tmdb.credits.42'));
        }

        $this->assertSame($this->credits(), $service->credits($titles)['42']);
        Http::assertSentCount(2);
    }

    public function test_connection_failures_are_wrapped_without_exposing_the_api_key(): void
    {
        config(['services.tmdb.key' => 'pool-secret-key']);
        Http::fake(fn () => Http::failedConnection('Connection failed for ?api_key=pool-secret-key'));
        try {
            app(MovieServiceInterface::class)->credits([['id' => 42, 'media_type' => 'movie']]);
            $this->fail('Expected TMDB failure.');
        } catch (TmdbUnavailable $error) {
            $this->assertStringNotContainsString('pool-secret-key', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}

<?php

namespace Tests\Feature\Person\Repository;

use App\Person\Exceptions\PersonNotFound;
use App\Person\InterfaceRepository\PersonRepositoryInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonRepositoryTest extends TestCase
{
    private function repository(): PersonRepositoryInterface
    {
        return app(PersonRepositoryInterface::class);
    }

    public function test_find_asks_for_the_person_with_their_movie_credits_in_one_call(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/525*' => Http::response(['id' => 525, 'name' => 'Christopher Nolan'])]);

        $payload = $this->repository()->find(525);

        $this->assertSame(['id' => 525, 'name' => 'Christopher Nolan'], $payload);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/person/525?')
            && $request['append_to_response'] === 'movie_credits'
            && $request['language'] === 'en-US'
            && $request['api_key'] === config('services.tmdb.key'));
        Http::assertSentCount(1);
    }

    public function test_find_throws_person_not_found_for_an_unknown_id(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['status_message' => 'Not found'], 404)]);

        $this->expectException(PersonNotFound::class);

        $this->repository()->find(999999999);
    }

    public function test_find_throws_tmdb_unavailable_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->find(525);
    }

    public function test_a_connection_failure_throws_tmdb_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->find(525);
    }

    public function test_movie_credits_calls_the_credits_endpoint_and_returns_the_payload(): void
    {
        $credits = ['id' => 27205, 'cast' => [['id' => 6193, 'name' => 'Leonardo DiCaprio']], 'crew' => [['id' => 525, 'job' => 'Director']]];
        Http::fake(['https://api.themoviedb.org/3/movie/27205/credits*' => Http::response($credits)]);

        $payload = $this->repository()->movieCredits(27205);

        $this->assertSame($credits, $payload);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/27205/credits?')
            && $request['api_key'] === config('services.tmdb.key'));
    }

    public function test_movie_credits_are_empty_for_a_movie_tmdb_no_longer_has(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['status_message' => 'Not found'], 404)]);

        $this->assertSame(['cast' => [], 'crew' => []], $this->repository()->movieCredits(999999999));
    }

    public function test_movie_credits_throw_tmdb_unavailable_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->movieCredits(27205);
    }
}

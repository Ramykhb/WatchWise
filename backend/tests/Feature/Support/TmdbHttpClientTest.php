<?php

namespace Tests\Feature\Support;

use App\Support\Exceptions\TmdbNotFound;
use App\Support\Exceptions\TmdbUnavailable;
use App\Support\TmdbHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbHttpClientTest extends TestCase
{
    public function test_a_connection_failure_does_not_leak_the_api_key(): void
    {
        config(['services.tmdb.key' => 'super-secret-key']);

        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out for https://api.themoviedb.org/3/movie/popular?api_key=super-secret-key&language=en-US',
        ));

        try {
            app(TmdbHttpClient::class)->get('/movie/popular');
            $this->fail('Expected TmdbUnavailable.');
        } catch (TmdbUnavailable $exception) {
            $this->assertStringContainsString('/movie/popular', $exception->getMessage());
            $this->assertStringNotContainsString('super-secret-key', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_a_404_throws_tmdb_not_found_instead_of_unavailable(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['status_message' => 'Not found'], 404)]);

        $this->expectException(TmdbNotFound::class);

        app(TmdbHttpClient::class)->get('/movie/999999999');
    }

    public function test_the_key_is_removed_even_when_it_is_not_in_a_query_string(): void
    {
        config(['services.tmdb.key' => 'super-secret-key']);

        $exception = TmdbUnavailable::fromFailure('/genre/movie/list', new ConnectionException('rejected token super-secret-key'));

        $this->assertStringNotContainsString('super-secret-key', $exception->getMessage());
    }
}

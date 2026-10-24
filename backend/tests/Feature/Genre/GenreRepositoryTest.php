<?php

namespace Tests\Feature\Genre;

use App\Support\Exceptions\TmdbUnavailable;
use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenreRepositoryTest extends TestCase
{
    public function test_it_returns_the_genres_from_the_genre_endpoint(): void
    {
        Http::fake(['https://api.themoviedb.org/3/genre/movie/list*' => Http::response([
            'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']],
        ])]);

        $genres = app(GenreRepositoryInterface::class)->all();

        $this->assertSame([['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']], $genres);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/genre/movie/list?')
            && $request['language'] === 'en-US');
    }

    public function test_a_tmdb_error_throws_tmdb_unavailable(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->expectException(TmdbUnavailable::class);

        app(GenreRepositoryInterface::class)->all();
    }

    public function test_a_connection_failure_throws_tmdb_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(TmdbUnavailable::class);

        app(GenreRepositoryInterface::class)->all();
    }
}

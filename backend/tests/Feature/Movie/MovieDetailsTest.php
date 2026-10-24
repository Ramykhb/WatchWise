<?php

namespace Tests\Feature\Movie;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieDetailsTest extends TestCase
{
    /**
     * A TMDB movie with credits, videos, similar movies and release dates appended.
     *
     * @return array<string, mixed>
     */
    private function tmdbMovie(): array
    {
        return [
            'id' => 81920,
            'title' => 'Neon Tide',
            'overview' => 'A glowing current.',
            'tagline' => 'Some memories glow.',
            'release_date' => '2026-03-06',
            'runtime' => 134,
            'status' => 'Released',
            'vote_average' => 8.14,
            'vote_count' => 1200,
            'budget' => 85000000,
            'revenue' => 0,
            'original_language' => 'en',
            'spoken_languages' => [['iso_639_1' => 'en', 'english_name' => 'English']],
            'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 12, 'name' => 'Adventure']],
            'poster_path' => '/neon.jpg',
            'backdrop_path' => '/neon-wide.jpg',
            'credits' => [
                'cast' => [['id' => 7, 'name' => 'Amara Mensah', 'character' => 'Dr. Ines Vale', 'profile_path' => '/amara.jpg']],
                'crew' => [
                    ['id' => 9, 'name' => 'Lena Okafor', 'job' => 'Director'],
                    ['id' => 10, 'name' => 'Sam Lee', 'job' => 'Editor'],
                ],
            ],
            'videos' => ['results' => [
                ['site' => 'YouTube', 'type' => 'Teaser', 'key' => 'teaser1', 'name' => 'Teaser'],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'fan1', 'name' => 'Fan trailer', 'official' => false],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'official1', 'name' => 'Official Trailer', 'official' => true],
            ]],
            'recommendations' => ['results' => [['id' => 65218, 'title' => 'Stardust', 'release_date' => '2025-11-21']]],
            'similar' => ['results' => [['id' => 69012, 'title' => 'Deep Blue', 'release_date' => '2025-08-15']]],
            'release_dates' => ['results' => [
                ['iso_3166_1' => 'FR', 'release_dates' => [['certification' => '12']]],
                ['iso_3166_1' => 'US', 'release_dates' => [['certification' => ''], ['certification' => 'PG-13']]],
            ]],
        ];
    }

    public function test_movie_details_returns_the_movie_with_its_extras(): void
    {
        Http::fake(['https://api.themoviedb.org/3/movie/81920*' => Http::response($this->tmdbMovie())]);

        $this->getJson('/api/movies/81920')
            ->assertOk()
            ->assertJsonPath('title', 'Neon Tide')
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('rating', 4.1)
            ->assertJsonPath('tmdb_score', 8.1)
            ->assertJsonPath('runtime', 134)
            ->assertJsonPath('genre_ids', [878, 12])
            ->assertJsonPath('genres.0.name', 'Science Fiction')
            ->assertJsonPath('original_language', ['code' => 'en', 'name' => 'English'])
            ->assertJsonPath('certification', 'PG-13')
            ->assertJsonPath('budget', 85000000)
            ->assertJsonPath('revenue', null)
            ->assertJsonPath('directors', [['id' => 9, 'name' => 'Lena Okafor']])
            ->assertJsonPath('cast.0.character', 'Dr. Ines Vale')
            ->assertJsonPath('cast.0.profile_url', 'https://image.tmdb.org/t/p/w185/amara.jpg')
            ->assertJsonPath('trailer.youtube_key', 'official1')
            ->assertJsonPath('trailer.embed_url', 'https://www.youtube.com/embed/official1')
            ->assertJsonPath('similar.0.title', 'Stardust');

        Http::assertSent(fn ($request) => $request['append_to_response'] === 'credits,videos,recommendations,similar,release_dates');
    }

    public function test_movie_details_fall_back_to_similar_movies_without_recommendations(): void
    {
        $movie = $this->tmdbMovie();
        $movie['recommendations'] = ['results' => []];

        Http::fake(['https://api.themoviedb.org/*' => Http::response($movie)]);

        $this->getJson('/api/movies/81920')->assertOk()->assertJsonPath('similar.0.title', 'Deep Blue');
    }

    public function test_movie_details_handle_a_movie_with_no_extras(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['id' => 1, 'title' => 'Bare'])]);

        $this->getJson('/api/movies/1')
            ->assertOk()
            ->assertJsonPath('trailer', null)
            ->assertJsonPath('certification', null)
            ->assertJsonPath('original_language', null)
            ->assertJsonPath('cast', [])
            ->assertJsonPath('similar', []);
    }

    public function test_movie_details_return_404_for_an_unknown_movie(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['status_message' => 'Not found'], 404)]);

        $this->getJson('/api/movies/999999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Movie not found.');
    }

    public function test_movie_details_return_502_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->getJson('/api/movies/81920')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Movie service is temporarily unavailable.');
    }

    public function test_movie_details_need_a_numeric_id(): void
    {
        $this->getJson('/api/movies/abc')->assertNotFound();
    }

    public function test_movie_details_are_cached_after_the_first_request(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbMovie())]);

        $this->getJson('/api/movies/81920')->assertOk();
        $this->getJson('/api/movies/81920')->assertOk()->assertJsonPath('title', 'Neon Tide');

        Http::assertSentCount(1);
    }

    public function test_an_unknown_movie_is_not_cached(): void
    {
        Http::fakeSequence('https://api.themoviedb.org/*')
            ->push(['status_message' => 'Not found'], 404)
            ->push($this->tmdbMovie());

        $this->getJson('/api/movies/81920')->assertNotFound();
        $this->getJson('/api/movies/81920')->assertOk();
    }

    public function test_movie_routes_are_rate_limited_per_ip(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbMovie())]);

        for ($request = 1; $request <= 60; $request++) {
            $this->getJson('/api/movies/81920')->assertOk();
        }

        $this->getJson('/api/movies/81920')->assertTooManyRequests();
        // The bucket is shared by every TMDB route.
        $this->getJson('/api/genres')->assertTooManyRequests();
    }
}

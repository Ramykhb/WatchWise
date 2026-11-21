<?php

namespace Tests\Feature\Movie;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_credits_and_safe_unique_videos_share_the_details_cache(): void
    {
        $cast = array_map(fn ($i) => ['id' => $i, 'name' => "Actor $i", 'character' => "Role $i"], range(1, 30));
        Http::fake(['https://api.themoviedb.org/3/movie/42*' => Http::response([
            'id' => 42, 'title' => 'A film', 'credits' => ['cast' => $cast, 'crew' => [['id' => 80, 'name' => 'Editor', 'job' => 'Editor', 'department' => 'Editing']]],
            'videos' => ['results' => [
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abc_123-XYZ', 'name' => 'Trailer', 'official' => true],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abc_123-XYZ', 'name' => 'Duplicate'],
                ['site' => 'Vimeo', 'type' => 'Trailer', 'key' => '123', 'name' => 'Unsupported'],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'bad/../../url', 'name' => 'Invalid'],
            ]],
        ])]);
        $this->getJson('/api/movies/42/extras')->assertOk()
            ->assertJsonCount(30, 'cast')->assertJsonPath('cast.29.character', 'Role 30')
            ->assertJsonPath('crew.0.department', 'Editing')->assertJsonCount(1, 'videos')
            ->assertJsonPath('videos.0.official', true);
        $this->getJson('/api/movies/42')->assertOk()->assertJsonCount(12, 'cast');
        Http::assertSentCount(1);
    }

    public function test_tv_and_missing_titles_and_invalid_types(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/42*' => Http::response(['id' => 42, 'name' => 'A show']),
            'https://api.themoviedb.org/3/movie/404*' => Http::response([], 404),
        ]);
        $this->getJson('/api/movies/42/extras?type=tv')->assertOk()->assertJsonPath('title', 'A show')->assertJsonPath('cast', [])->assertJsonPath('videos', []);
        $this->getJson('/api/movies/404/extras')->assertNotFound();
        $this->getJson('/api/movies/42/extras?type=unknown')->assertUnprocessable();
    }
}

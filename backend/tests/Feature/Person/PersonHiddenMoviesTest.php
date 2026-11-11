<?php

namespace Tests\Feature\Person;

use App\Models\LocalMovie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\TestCase;

class PersonHiddenMoviesTest extends TestCase
{
    use RefreshDatabase;
    use UsesMovieCatalogue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealCatalogue();
        Http::fake(['https://api.themoviedb.org/3/person/8767*' => Http::response([
            'id' => 8767,
            'name' => 'Lena Okafor',
            'movie_credits' => [
                'cast' => [['id' => 1, 'title' => 'Old Film', 'release_date' => '2019-05-01', 'vote_count' => 10, 'character' => 'Herself']],
                'crew' => [['id' => 2, 'title' => 'Neon Tide', 'release_date' => '2026-03-06', 'vote_count' => 900, 'job' => 'Director']],
            ],
        ])]);
    }

    public function test_a_hidden_film_is_left_out_of_a_filmography_and_known_for(): void
    {
        $this->hide(2);

        $this->getJson('/api/people/8767')
            ->assertOk()
            ->assertJsonCount(1, 'credits')
            ->assertJsonPath('credits.0.title', 'Old Film')
            ->assertJsonCount(1, 'known_for')
            ->assertJsonPath('known_for.0.title', 'Old Film');
    }

    public function test_showing_a_film_again_applies_while_the_person_is_cached(): void
    {
        $film = LocalMovie::factory()->hidden()->create(['tmdb_id' => 2]);

        $this->getJson('/api/people/8767')->assertOk()->assertJsonCount(1, 'credits');

        $film->update(['is_hidden' => false]);

        $this->getJson('/api/people/8767')->assertOk()->assertJsonCount(2, 'credits');
        Http::assertSentCount(1);
    }
}

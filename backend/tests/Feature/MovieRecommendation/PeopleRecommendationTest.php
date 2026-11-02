<?php

namespace Tests\Feature\MovieRecommendation;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\User;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Person\InterfaceRepository\PersonRepositoryInterface;
use Tests\Feature\Social\SocialTestCase;

class PeopleRecommendationTest extends SocialTestCase
{
    private function prepareHistory(bool $largeFilmography = false, int $creditsCalls = 1): mixed
    {
        $user = User::factory()->create();
        $this->as($user);
        foreach ([42, 43] as $id) {
            $this->postJson('/api/watchlist', ['tmdb_id' => $id, 'status' => 'watched'])->assertCreated();
        }
        $this->postJson('/api/watchlist', ['tmdb_id' => 99, 'status' => 'to_watch'])->assertCreated();
        $other = User::factory()->create();
        $this->as($other)->postJson('/api/watchlist', ['tmdb_id' => 999, 'status' => 'watched'])->assertCreated();
        $this->as($user);
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('credits')->times($creditsCalls)->withArgs(function (array $titles): bool {
            $this->assertEqualsCanonicalizing([
                ['id' => 42, 'media_type' => 'movie'], ['id' => 43, 'media_type' => 'movie'],
            ], $titles);

            return true;
        })->andReturn([
            '42' => ['cast' => [['id' => 10, 'name' => 'Top Actor'], ['id' => 10, 'name' => 'Top Actor'], ['id' => 42, 'name' => 'Other Actor']],
                'crew' => [['id' => 20, 'name' => 'Top Director', 'job' => 'Director'], ['id' => 30, 'name' => 'Writer', 'job' => 'Writer']]],
            '43' => ['cast' => [['id' => 10, 'name' => 'Top Actor'], ['id' => 43, 'name' => 'Other Actor']],
                'crew' => [['id' => 20, 'name' => 'Top Director', 'job' => 'Director']]],
        ]);
        $film = fn (int $id): array => ['id' => $id, 'title' => "Film {$id}", 'release_date' => '2020-01-01', 'vote_count' => 100, 'vote_average' => 8, 'genre_ids' => [18], 'poster_path' => '/poster.jpg', 'overview' => $largeFilmography ? str_repeat('Long overview. ', 1000) : 'Drama.'];
        $people = $this->mock(PersonRepositoryInterface::class);
        $people->shouldReceive('find')->once()->with(20)->andReturn(['movie_credits' => ['crew' => [
            ...array_map(fn ($id) => $film($id) + ['job' => 'Director'], ($largeFilmography ? range(1, 40) : [42, 1, 2, 3])), $film(9999) + ['job' => 'Writer'],
        ]]]);
        $people->shouldReceive('find')->once()->with(10)->andReturn(['movie_credits' => ['cast' => array_map($film, $largeFilmography ? range(50, 90) : [43, 3, 4, 5, 6])]]);

        return $this->mock(GroqStructuredOutputInterface::class);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/people-recommendations')->assertUnauthorized();
    }

    public function test_empty_history_does_not_call_providers(): void
    {
        $this->as(User::factory()->create());
        $this->mock(MovieServiceInterface::class)->shouldNotReceive('credits');
        $this->mock(GroqStructuredOutputInterface::class)->shouldNotReceive('askWithSchema');
        $this->getJson('/api/people-recommendations')->assertOk()->assertExactJson(['director' => null, 'actor' => null, 'movies' => []]);
    }

    public function test_it_counts_watched_credits_and_returns_six_verified_key_two_choices(): void
    {
        $groq = $this->prepareHistory();
        $groq->shouldReceive('askWithSchema')->once()->withArgs(function ($prompt, $name, $schema, $tokens, $keyProfile) {
            $this->assertSame('watched', $keyProfile);
            $this->assertSame(6, $schema['properties']['movies']['minItems']);
            $this->assertStringContainsString('Top Director', $prompt);
            $this->assertStringContainsString('Top Actor', $prompt);
            foreach (['Film 42', 'Film 43', 'Film 9999'] as $excluded) {
                $this->assertStringNotContainsString($excluded, $prompt);
            }

            return true;
        })->andReturn(['movies' => array_map(fn ($id) => ['id' => $id, 'media_type' => 'movie', 'reason' => 'A favourite credit.'], range(1, 6))]);
        $first = $this->getJson('/api/people-recommendations')->assertOk()->assertJsonPath('director.id', 20)->assertJsonPath('actor.id', 10)
            ->assertJsonPath('actor.watched_count', 2)->assertJsonPath('director.watched_count', 2)->assertJsonCount(6, 'movies')->json();
        $this->getJson('/api/people-recommendations')->assertOk()->assertExactJson($first);
        foreach ($this->getJson('/api/watchlist')->json('items') as $entry) {
            if ($entry['status'] === 'watched') {
                $this->patchJson('/api/watchlist/'.$entry['id'], ['status' => 'to_watch'])->assertOk();
            }
        }
        $this->getJson('/api/people-recommendations')->assertOk()->assertExactJson(['director' => null, 'actor' => null, 'movies' => []]);
    }

    public function test_large_filmographies_use_a_compact_prompt_but_keep_full_display_details(): void
    {
        $groq = $this->prepareHistory(largeFilmography: true);
        $groq->shouldReceive('askWithSchema')->once()->withArgs(function ($prompt) {
            $candidates = json_decode(explode('. TMDB candidates: ', $prompt)[1], true);
            $this->assertCount(24, $candidates);
            $this->assertLessThan(10000, strlen($prompt));
            foreach ($candidates as $candidate) {
                $this->assertLessThanOrEqual(160, mb_strlen($candidate['overview']));
                $this->assertArrayNotHasKey('poster_url', $candidate);
                $this->assertArrayNotHasKey('backdrop_url', $candidate);
            }

            return true;
        })->andReturn(['movies' => array_map(fn ($id) => ['id' => $id, 'media_type' => 'movie', 'reason' => 'A favourite credit.'], range(1, 6))]);

        $this->getJson('/api/people-recommendations')->assertOk()->assertJsonCount(6, 'movies')
            ->assertJsonPath('movies.0.overview', str_repeat('Long overview. ', 1000))
            ->assertJsonPath('movies.0.poster_url', 'https://image.tmdb.org/t/p/w500/poster.jpg');
    }

    public function test_it_rejects_invented_watched_duplicate_and_wrong_media_choices(): void
    {
        $groq = $this->prepareHistory();
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => [
            ['id' => 9999, 'media_type' => 'movie', 'reason' => 'Invented'], ['id' => 42, 'media_type' => 'movie', 'reason' => 'Watched'],
            ['id' => 1, 'media_type' => 'tv', 'reason' => 'Wrong type'], ['id' => 1, 'media_type' => 'movie', 'reason' => 'Valid'],
            ['id' => 1, 'media_type' => 'movie', 'reason' => 'Duplicate'],
        ]]);
        $this->getJson('/api/people-recommendations')->assertStatus(502)->assertExactJson(['error' => 'Could not generate recommendations']);
    }

    public function test_groq_errors_are_safe_and_retryable(): void
    {
        $groq = $this->prepareHistory(creditsCalls: 2);
        $groq->shouldReceive('askWithSchema')->twice()->andThrow(new GroqUnavailable('SECRET'));
        foreach (range(1, 2) as $_) {
            $this->getJson('/api/people-recommendations')->assertStatus(502)->assertExactJson(['error' => 'Could not generate recommendations']);
        }
    }
}

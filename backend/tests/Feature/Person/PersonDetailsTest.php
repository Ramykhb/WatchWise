<?php

namespace Tests\Feature\Person;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonDetailsTest extends TestCase
{
    /**
     * A TMDB person who acted in one film and directed and wrote another.
     *
     * @return array<string, mixed>
     */
    private function tmdbPerson(): array
    {
        return [
            'id' => 8767,
            'name' => 'Lena Okafor',
            'biography' => 'Filmmaker known for ocean-soaked science fiction.',
            'birthday' => '1984-02-03',
            'deathday' => null,
            'place_of_birth' => 'Lagos, Nigeria',
            'known_for_department' => 'Directing',
            'profile_path' => '/lena.jpg',
            'movie_credits' => [
                'cast' => [
                    ['id' => 1, 'title' => 'Old Film', 'release_date' => '2019-05-01', 'poster_path' => '/old.jpg', 'vote_average' => 6.2, 'vote_count' => 10, 'character' => 'Herself'],
                ],
                'crew' => [
                    ['id' => 2, 'title' => 'Neon Tide', 'release_date' => '2026-03-06', 'poster_path' => '/neon.jpg', 'vote_average' => 8.1, 'vote_count' => 900, 'job' => 'Director'],
                    ['id' => 2, 'title' => 'Neon Tide', 'release_date' => '2026-03-06', 'poster_path' => '/neon.jpg', 'vote_average' => 8.1, 'vote_count' => 900, 'job' => 'Screenplay'],
                ],
            ],
        ];
    }

    public function test_person_details_returns_the_profile_and_one_credit_per_film(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson())]);

        $this->getJson('/api/people/8767')
            ->assertOk()
            ->assertJsonPath('id', 8767)
            ->assertJsonPath('name', 'Lena Okafor')
            ->assertJsonPath('place_of_birth', 'Lagos, Nigeria')
            ->assertJsonPath('known_for_department', 'Directing')
            ->assertJsonPath('profile_url', config('services.tmdb.image_url').'/h632/lena.jpg')
            ->assertJsonCount(2, 'credits')
            // Newest first, and the two crew jobs on Neon Tide become one film with two roles.
            ->assertJsonPath('credits.0.title', 'Neon Tide')
            ->assertJsonPath('credits.0.year', 2026)
            ->assertJsonPath('credits.0.roles', ['Director', 'Screenplay'])
            ->assertJsonPath('credits.0.rating', 4.1)
            ->assertJsonPath('credits.1.roles', ['Actor'])
            ->assertJsonPath('credits.1.character', 'Herself')
            // The most-voted film leads "Known for".
            ->assertJsonPath('known_for.0.title', 'Neon Tide');
    }

    public function test_person_details_are_cached(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson())]);

        $this->getJson('/api/people/8767')->assertOk();
        $this->getJson('/api/people/8767')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_unknown_person_returns_404(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/*' => Http::response(['status_message' => 'The resource you requested could not be found.'], 404)]);

        $this->getJson('/api/people/999999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Person not found.');
    }

    public function test_tmdb_failure_returns_502(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/*' => Http::response([], 500)]);

        $this->getJson('/api/people/8767')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Movie service is temporarily unavailable.');
    }

    public function test_non_numeric_id_is_not_found(): void
    {
        $this->getJson('/api/people/abc')->assertNotFound();
    }

    /**
     * TMDB credits for one of the person's two films (1: Old Film, 2: Neon Tide).
     *
     * @param  list<array<string, mixed>>  $cast
     * @param  list<array<string, mixed>>  $crew
     * @return array<string, mixed>
     */
    private function movieCredits(array $cast, array $crew = []): array
    {
        return ['cast' => $cast, 'crew' => $crew];
    }

    public function test_collaborators_are_people_in_at_least_two_of_their_films(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson()),
            'https://api.themoviedb.org/3/movie/2/credits*' => Http::response($this->movieCredits(
                cast: [
                    ['id' => 8767, 'name' => 'Lena Okafor'],
                    ['id' => 50, 'name' => 'Amara Mensah', 'profile_path' => '/amara.jpg'],
                    ['id' => 60, 'name' => 'One-Off Actor'],
                ],
                crew: [
                    ['id' => 70, 'name' => 'Tom Brennan', 'job' => 'Original Music Composer'],
                    ['id' => 80, 'name' => 'Dan Driver', 'job' => 'Driver'],
                ],
            )),
            'https://api.themoviedb.org/3/movie/1/credits*' => Http::response($this->movieCredits(
                cast: [['id' => 50, 'name' => 'Amara Mensah', 'profile_path' => '/amara.jpg']],
                crew: [
                    ['id' => 70, 'name' => 'Tom Brennan', 'job' => 'Original Music Composer'],
                    ['id' => 80, 'name' => 'Dan Driver', 'job' => 'Driver'],
                ],
            )),
        ]);

        // Not the person themself, not someone in one film, not a crew job outside the key ones.
        $this->getJson('/api/people/8767/collaborators')
            ->assertOk()
            ->assertJsonCount(2, 'collaborators')
            ->assertJsonPath('collaborators.0.name', 'Amara Mensah')
            ->assertJsonPath('collaborators.0.role', 'Actor')
            ->assertJsonPath('collaborators.0.films', 2)
            ->assertJsonPath('collaborators.0.profile_url', config('services.tmdb.image_url').'/w185/amara.jpg')
            ->assertJsonPath('collaborators.1.name', 'Tom Brennan')
            ->assertJsonPath('collaborators.1.role', 'Original Music Composer');
    }

    public function test_collaborators_are_limited_to_three(): void
    {
        $shared = array_map(fn (int $id): array => ['id' => $id, 'name' => "Actor {$id}"], [101, 102, 103, 104]);
        Http::fake([
            'https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson()),
            'https://api.themoviedb.org/3/movie/*/credits*' => Http::response($this->movieCredits($shared)),
        ]);

        $this->getJson('/api/people/8767/collaborators')
            ->assertOk()
            ->assertJsonCount(3, 'collaborators');
    }

    public function test_collaborators_are_cached(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson()),
            'https://api.themoviedb.org/3/movie/*/credits*' => Http::response($this->movieCredits([['id' => 50, 'name' => 'Amara Mensah']])),
        ]);

        $this->getJson('/api/people/8767/collaborators')->assertOk();
        $this->getJson('/api/people/8767/collaborators')->assertOk();

        // The person and each of their two films' credits, once.
        Http::assertSentCount(3);
    }

    public function test_collaborators_are_empty_when_tmdb_fails(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/person/8767*' => Http::response($this->tmdbPerson()),
            'https://api.themoviedb.org/3/movie/*/credits*' => Http::response([], 500),
        ]);

        $this->getJson('/api/people/8767/collaborators')
            ->assertOk()
            ->assertJsonPath('collaborators', []);
    }

    public function test_collaborators_of_an_unknown_person_return_404(): void
    {
        Http::fake(['https://api.themoviedb.org/3/person/*' => Http::response(['status_message' => 'The resource you requested could not be found.'], 404)]);

        $this->getJson('/api/people/999999999/collaborators')
            ->assertNotFound()
            ->assertJsonPath('message', 'Person not found.');
    }
}

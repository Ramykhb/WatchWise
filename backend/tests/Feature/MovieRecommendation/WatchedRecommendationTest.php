<?php

namespace Tests\Feature\MovieRecommendation;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\User;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Social\SocialTestCase;

class WatchedRecommendationTest extends SocialTestCase
{
    private function movie(int $id, string $type = 'movie'): MovieResponse
    {
        return new MovieResponse($id, "Title {$type} {$id}", 'Overview', null, null, 4, 50, [18], null, null, $type);
    }

    private function prepareCandidates(int $generationCalls = 1, bool $relatedAvailable = false, bool $discoveryFails = false, bool $sameTitleCandidate = false): array
    {
        $user = User::factory()->create();
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'watched'])->assertCreated();
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 99, 'status' => 'to_watch'])->assertCreated();
        $other = User::factory()->create();
        $this->as($other)->postJson('/api/watchlist', ['tmdb_id' => 999, 'status' => 'watched'])->assertCreated();
        $this->as($user);
        $candidates = [$this->movie(42), $this->movie(43, 'tv'), $this->movie(44),
            $this->movie(45), $this->movie(46), $this->movie(47)];
        if ($sameTitleCandidate) {
            array_unshift($candidates, new MovieResponse(500, '  TITLE TV 42  ', 'Another edition', null, null, 4, 50, [18], null, null));
        }
        $source = new MovieDetailsResponse(
            $this->movie(42, 'tv'), [new GenreResponse(18, 'Drama')], null, null, null, 8, null, null,
            null, null, [], [], null, $relatedAvailable ? [...$candidates, $this->movie(42, 'tv')] : ($discoveryFails ? array_slice($candidates, 0, 2) : []),
        );
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('find')->times($generationCalls)->with(42, 'tv')->andReturn($source);
        if ($relatedAvailable) {
            $movies->shouldNotReceive('discover');
        } elseif ($discoveryFails) {
            $movies->shouldReceive('discover')->once()->andThrow(new TmdbUnavailable);
        } else {
            $movies->shouldReceive('discover')->times($generationCalls)->withArgs(fn ($query) => $query->type === 'all'
                && $query->genres === '18' && $query->tvGenres === '18')
                ->andReturn(new MoviePageResponse(1, 1, 7, [...$candidates, $this->movie(42, 'tv')]));
        }
        $groq = $this->mock(GroqStructuredOutputInterface::class);

        return [$groq, $candidates];
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/watched-recommendations')->assertUnauthorized();
    }

    public function test_it_hides_the_shelf_without_watched_titles_even_if_other_users_have_history(): void
    {
        $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 42, 'status' => 'watched'])->assertCreated();
        $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 99, 'status' => 'watching'])->assertCreated();
        $this->mock(MovieServiceInterface::class)->shouldNotReceive('find', 'discover');
        $this->mock(GroqStructuredOutputInterface::class)->shouldNotReceive('askWithSchema');

        $this->getJson('/api/watched-recommendations')->assertOk()->assertExactJson(['source' => null, 'movies' => []]);
    }

    public function test_it_returns_six_groq_choices_with_correct_media_types_from_the_users_history(): void
    {
        [$groq, $candidates] = $this->prepareCandidates();
        $groq->shouldReceive('askWithSchema')->once()->withArgs(function ($prompt, $name, $schema, $maxCompletionTokens, $keyProfile) {
            $this->assertSame('watched', $keyProfile);
            $this->assertStringContainsString('Title tv 42', $prompt);
            $this->assertStringNotContainsString('Title movie 999', $prompt);
            $this->assertStringNotContainsString('Title movie 99', $prompt);
            $candidateJson = explode('. TMDB candidates: ', $prompt)[1];
            $this->assertStringNotContainsString('Title tv 42', $candidateJson);

            return true;
        })->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'A related drama.',
        ], $candidates)]);

        $this->getJson('/api/watched-recommendations')->assertOk()
            ->assertJsonPath('source.id', 42)->assertJsonPath('source.media_type', 'tv')
            ->assertJsonCount(6, 'movies')->assertJsonPath('movies.0.id', 42)
            ->assertJsonPath('movies.0.media_type', 'movie')->assertJsonPath('movies.1.media_type', 'tv')
            ->assertJsonPath('movies.0.reason', 'A related drama.');
    }

    public function test_it_uses_existing_related_titles_without_an_extra_tmdb_discovery_request(): void
    {
        [$groq, $candidates] = $this->prepareCandidates(relatedAvailable: true);
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], $candidates)]);

        $this->getJson('/api/watched-recommendations')->assertOk()
            ->assertJsonCount(6, 'movies')->assertJsonPath('movies.0.media_type', 'movie');
    }

    public static function candidateSources(): array
    {
        return ['related titles' => [true], 'discovery' => [false]];
    }

    #[DataProvider('candidateSources')]
    public function test_it_excludes_another_tmdb_edition_with_the_same_title_as_the_watched_source(bool $relatedAvailable): void
    {
        [$groq, $candidates] = $this->prepareCandidates(relatedAvailable: $relatedAvailable, sameTitleCandidate: true);
        $groq->shouldReceive('askWithSchema')->once()->withArgs(function ($prompt) {
            $candidateJson = explode('. TMDB candidates: ', $prompt)[1];
            $this->assertStringNotContainsString('Another edition', $candidateJson);

            return true;
        })->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], array_slice($candidates, 1))]);

        $this->getJson('/api/watched-recommendations')->assertOk()
            ->assertJsonCount(6, 'movies')->assertJsonMissing(['id' => 500]);
    }

    public function test_it_keeps_available_related_titles_when_discovery_times_out(): void
    {
        [$groq, $candidates] = $this->prepareCandidates(discoveryFails: true);
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], array_slice($candidates, 0, 2))]);

        $this->getJson('/api/watched-recommendations')->assertOk()->assertJsonCount(2, 'movies');
    }

    public function test_it_reuses_successful_results_for_an_hour_then_generates_again(): void
    {
        [$groq, $candidates] = $this->prepareCandidates(2);
        $groq->shouldReceive('askWithSchema')->twice()->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], $candidates)]);

        $first = $this->getJson('/api/watched-recommendations')->assertOk()->json();
        $this->getJson('/api/watched-recommendations')->assertOk()->assertExactJson($first);
        $this->travel(61)->minutes();
        $this->getJson('/api/watched-recommendations')->assertOk()->assertJsonCount(6, 'movies');
    }

    public function test_repeat_requests_work_with_file_cache_when_class_deserialization_is_disabled(): void
    {
        $cachePath = storage_path('framework/testing/watched-cache-'.Str::uuid());
        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $cachePath,
            'cache.serializable_classes' => false,
        ]);
        [$groq, $candidates] = $this->prepareCandidates();
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], $candidates)]);

        try {
            $first = $this->getJson('/api/watched-recommendations')->assertOk()->json();
            $this->getJson('/api/watched-recommendations')->assertOk()->assertExactJson($first);
        } finally {
            File::deleteDirectory($cachePath);
        }
    }

    public function test_it_does_not_show_cached_picks_after_history_is_cleared(): void
    {
        [$groq, $candidates] = $this->prepareCandidates();
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => array_map(fn ($movie) => [
            'id' => $movie->id, 'media_type' => $movie->mediaType, 'reason' => 'Related drama.',
        ], $candidates)]);
        $this->getJson('/api/watched-recommendations')->assertOk()->assertJsonCount(6, 'movies');
        $entry = collect($this->getJson('/api/watchlist')->json('items'))->firstWhere('status', 'watched');
        $this->patchJson('/api/watchlist/'.$entry['id'], ['status' => 'to_watch'])->assertOk();

        $this->getJson('/api/watched-recommendations')->assertOk()->assertExactJson(['source' => null, 'movies' => []]);
    }

    public function test_it_rejects_invented_watched_and_duplicate_groq_selections(): void
    {
        [$groq] = $this->prepareCandidates();
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => [
            ['id' => 999, 'media_type' => 'movie', 'reason' => 'Invented'],
            ['id' => 42, 'media_type' => 'tv', 'reason' => 'Already watched'],
            ['id' => 42, 'media_type' => 'movie', 'reason' => 'Valid'],
            ['id' => 42, 'media_type' => 'movie', 'reason' => 'Duplicate'],
        ]]);

        $this->getJson('/api/watched-recommendations')->assertStatus(502)
            ->assertExactJson(['error' => 'Could not generate recommendations']);
    }

    public function test_it_handles_groq_failure_without_leaking_provider_details(): void
    {
        [$groq] = $this->prepareCandidates();
        $groq->shouldReceive('askWithSchema')->once()->andThrow(new GroqUnavailable('SECRET'));

        $this->getJson('/api/watched-recommendations')->assertStatus(502)
            ->assertExactJson(['error' => 'Could not generate recommendations']);
    }

    public function test_it_handles_removed_tmdb_titles(): void
    {
        $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 42, 'status' => 'watched'])->assertCreated();
        $this->mock(MovieServiceInterface::class)->shouldReceive('find')->once()->with(42, 'movie')->andThrow(new MovieNotFound(42));
        $this->mock(GroqStructuredOutputInterface::class)->shouldNotReceive('askWithSchema');

        $this->getJson('/api/watched-recommendations')->assertOk()->assertExactJson(['source' => null, 'movies' => []]);
    }
}

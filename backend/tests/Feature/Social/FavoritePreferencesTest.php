<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class FavoritePreferencesTest extends SocialTestCase
{
    public function test_preferences_round_trip_and_empty_arrays_clear_them_without_changing_privacy(): void
    {
        $user = User::factory()->create();
        $this->as($user)->patchJson('/api/me/preferences', [
            'favorite_genres' => ['Drama', 'Comedy'], 'favorite_actor_ids' => [31, 32],
            'favorite_director_ids' => [99], 'privacy' => ['history' => false],
        ])->assertOk()->assertJsonPath('preferences.favorite_actor_ids', [31, 32]);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('preferences.favorite_director_ids', [99])
            ->assertJsonPath('preferences.favorite_genres', ['Drama', 'Comedy']);
        $this->patchJson('/api/me/preferences', ['favorite_actor_ids' => [], 'favorite_director_ids' => [], 'favorite_genres' => []])
            ->assertOk()->assertJsonPath('preferences.favorite_actor_ids', [])->assertJsonPath('preferences.favorite_director_ids', [])
            ->assertJsonPath('preferences.favorite_genres', [])->assertJsonPath('preferences.privacy.history', false);
    }

    public function test_invalid_person_preferences_are_rejected_and_never_saved(): void
    {
        $this->as(User::factory()->create());
        foreach ([[0], [-1], [1, 1], [1, 2, 3, 4, 5, 6], ['not an id']] as $ids) {
            $this->patchJson('/api/me/preferences', ['favorite_actor_ids' => $ids])->assertUnprocessable();
            $this->patchJson('/api/me/preferences', ['favorite_director_ids' => $ids])->assertUnprocessable();
        }
    }

    public function test_person_search_validates_the_query_and_returns_only_public_picker_fields(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*search/person*' => Http::response(['results' => [
            ['id' => 31, 'name' => 'Example Actor', 'known_for_department' => 'Acting', 'profile_path' => null, 'popularity' => 20],
        ]])]);
        $this->getJson('/api/people/search?q=A')->assertUnprocessable();
        $this->getJson('/api/people/search?q=Example')->assertOk()->assertExactJson(['people' => [
            ['id' => 31, 'name' => 'Example Actor', 'department' => 'Acting', 'profile_url' => null],
        ]]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search/person') && $request['query'] === 'Example');
    }
}

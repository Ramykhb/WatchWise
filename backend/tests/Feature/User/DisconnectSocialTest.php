<?php

namespace Tests\Feature\User;

use App\Models\SocialAccount;
use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class DisconnectSocialTest extends SocialTestCase
{
    /** An account created by social sign-in that never set a password. */
    private function socialOnlyUser(string ...$providers): User
    {
        $user = User::factory()->withoutPassword()->create();
        foreach ($providers as $provider) {
            $this->link($user, $provider);
        }

        return $user;
    }

    private function link(User $user, string $provider): void
    {
        SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => $provider.'-'.$user->id]);
    }

    /** @return list<string> */
    private function linkedProviders(User $user): array
    {
        return $user->socialAccounts()->orderBy('provider')->pluck('provider')->all();
    }

    public function test_an_account_without_a_password_cannot_disconnect_its_last_provider(): void
    {
        $user = $this->socialOnlyUser('google');

        $this->as($user)->deleteJson('/api/me/social/google')
            ->assertUnprocessable()
            ->assertJsonPath('errors.provider.0', 'Google is the only way you can sign in. Create a password or connect another account before disconnecting it.');

        $this->assertSame(['google'], $this->linkedProviders($user));
    }

    public function test_an_account_without_a_password_can_disconnect_while_another_provider_remains(): void
    {
        $user = $this->socialOnlyUser('google', 'github');

        $this->as($user)->deleteJson('/api/me/social/google')
            ->assertOk()
            ->assertJsonMissingPath('connected.google')
            ->assertJsonPath('connected.github', fn ($since) => $since !== null);

        $this->assertSame(['github'], $this->linkedProviders($user));
        $this->as($user)->deleteJson('/api/me/social/github')->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->assertSame(['github'], $this->linkedProviders($user));
    }

    public function test_an_account_with_a_password_can_disconnect_its_last_provider(): void
    {
        $user = User::factory()->create();
        $this->link($user, 'discord');

        $this->as($user)->deleteJson('/api/me/social/discord')->assertOk();

        $this->assertSame([], $this->linkedProviders($user));
    }

    public function test_disconnecting_a_provider_that_is_not_linked_changes_nothing(): void
    {
        $user = $this->socialOnlyUser('google');
        $unlinked = $this->socialOnlyUser();

        $this->as($user)->deleteJson('/api/me/social/github')->assertOk()->assertJsonPath('connected.google', fn ($since) => $since !== null);
        $this->as($unlinked)->deleteJson('/api/me/social/github')->assertOk();

        $this->assertSame(['google'], $this->linkedProviders($user));
    }
}

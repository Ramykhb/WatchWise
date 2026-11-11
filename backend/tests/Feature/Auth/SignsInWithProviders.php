<?php

namespace Tests\Feature\Auth;

use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

/**
 * Drives the social sign-in callback the way a provider does: real-looking OAuth config (never from .env), a faked
 * Socialite driver, and the `oauth_state` cookie the redirect would have set.
 */
trait SignsInWithProviders
{
    private function configureSocialProviders(): void
    {
        foreach (['google', 'github', 'discord'] as $provider) {
            config(["services.{$provider}" => [
                'client_id' => "{$provider}-client-id",
                'client_secret' => "{$provider}-client-secret",
                'redirect' => "http://api.test/api/auth/social/{$provider}/callback",
            ]]);
        }
    }

    /**
     * @param  array<string, mixed>  $raw  the provider's raw profile, e.g. `['email_verified' => true]` for Google
     */
    private function socialiteUser(string $id, ?string $email, array $raw = [], ?string $avatar = null): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map(['id' => $id, 'name' => 'Soc User', 'nickname' => 'soc', 'email' => $email, 'avatar' => $avatar]);
        $user->token = 'provider-access-token';
        $user->user = $raw;

        return $user;
    }

    private function mockSocialite(string $provider, SocialiteUser $socialUser): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    /** The callback with a state issued for `$provider`; `$query` is appended (e.g. `&code=x`). */
    private function hitSocialCallback(string $provider, string $query = ''): TestResponse
    {
        $state = "{$provider}:abc";

        return $this->withUnencryptedCookie('oauth_state', $state)
            ->get("/api/auth/social/{$provider}/callback?state={$state}{$query}");
    }
}

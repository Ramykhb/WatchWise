<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** An access token is accepted only when it is signed with our secret and HS256 and names our issuer and audience. */
class AccessTokenValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $overrides */
    private function claims(User $user, array $overrides = []): array
    {
        return array_merge([
            'iss' => config('jwt.issuer'),
            'aud' => config('jwt.audience'),
            'sub' => (string) $user->id,
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'sv' => 0,
        ], $overrides);
    }

    private function meWith(string $jwt): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$jwt)->getJson('/api/auth/me');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function test_a_token_built_like_ours_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->meWith(JWT::encode($this->claims($user), config('jwt.secret'), 'HS256'))->assertOk();
    }

    public function test_a_token_signed_with_another_secret_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->meWith(JWT::encode($this->claims($user), str_repeat('x', 40), 'HS256'))->assertUnauthorized();
    }

    public function test_an_unsigned_alg_none_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = $this->base64Url(json_encode($this->claims($user)));

        $this->meWith("{$header}.{$payload}.")->assertUnauthorized();
    }

    public function test_a_token_signed_with_another_algorithm_is_rejected(): void
    {
        $user = User::factory()->create();

        // HMAC-SHA512 over our own secret, built by hand: the library refuses keys shorter than 512 bits.
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS512']));
        $payload = $this->base64Url(json_encode($this->claims($user)));
        $signature = $this->base64Url(hash_hmac('sha512', "{$header}.{$payload}", config('jwt.secret'), true));

        $this->meWith("{$header}.{$payload}.{$signature}")->assertUnauthorized();
    }

    public function test_a_token_for_another_issuer_or_audience_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->meWith(JWT::encode($this->claims($user, ['iss' => 'https://staging.example.com']), config('jwt.secret'), 'HS256'))
            ->assertUnauthorized();
        $this->meWith(JWT::encode($this->claims($user, ['aud' => 'staging.example.com']), config('jwt.secret'), 'HS256'))
            ->assertUnauthorized();
        $this->meWith(JWT::encode($this->claims($user, ['aud' => [config('jwt.audience')]]), config('jwt.secret'), 'HS256'))
            ->assertUnauthorized();
    }

    public function test_a_token_without_issuer_or_audience_is_accepted_only_while_the_switch_is_off(): void
    {
        $user = User::factory()->create();
        $withoutAudience = $this->claims($user);
        unset($withoutAudience['aud']);
        $withoutIssuer = $this->claims($user);
        unset($withoutIssuer['iss']);
        $tokens = [
            JWT::encode($withoutAudience, config('jwt.secret'), 'HS256'),
            JWT::encode($withoutIssuer, config('jwt.secret'), 'HS256'),
        ];

        foreach ($tokens as $jwt) {
            $this->meWith($jwt)->assertUnauthorized();
        }

        config(['jwt.require_iss_aud' => false]);

        foreach ($tokens as $jwt) {
            $this->meWith($jwt)->assertOk();
        }

        // A claim that is present must still match.
        $this->meWith(JWT::encode($this->claims($user, ['iss' => 'https://staging.example.com']), config('jwt.secret'), 'HS256'))
            ->assertUnauthorized();
    }
}

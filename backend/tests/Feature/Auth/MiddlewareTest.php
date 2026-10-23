<?php

namespace Tests\Feature\Auth;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Factories\TokenFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('role:admin')->get('/api/_test/admin', fn () => ['ok' => true]);
        Route::middleware('verified.email')->get('/api/_test/verified', fn () => ['ok' => true]);
        Route::middleware('auth.optional')->get('/api/_test/optional', fn (CurrentUser $current) => [
            'authenticated' => $current->isAuthenticated(),
            'id' => $current->id(),
        ]);
    }

    private function as(User $user): static
    {
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        return $this->withHeader('Authorization', 'Bearer '.$jwt);
    }

    public function test_role_middleware(): void
    {
        $this->getJson('/api/_test/admin')->assertUnauthorized();
        $this->as(User::factory()->create())->getJson('/api/_test/admin')->assertForbidden();
        $this->as(User::factory()->admin()->create())->getJson('/api/_test/admin')->assertOk();
    }

    public function test_role_comes_from_the_database_not_the_stale_token(): void
    {
        $user = User::factory()->admin()->create();
        $client = $this->as($user);
        $user->forceFill(['role' => 'user'])->save();

        $client->getJson('/api/_test/admin')->assertForbidden();
    }

    public function test_verified_email_middleware(): void
    {
        $this->getJson('/api/_test/verified')->assertUnauthorized();
        $this->as(User::factory()->unverified()->create())->getJson('/api/_test/verified')->assertForbidden();
        $this->as(User::factory()->create())->getJson('/api/_test/verified')->assertOk();
    }

    public function test_optional_auth_allows_guests_and_resolves_users(): void
    {
        $this->getJson('/api/_test/optional')->assertOk()->assertJson(['authenticated' => false, 'id' => null]);

        $user = User::factory()->create();
        $this->as($user)->getJson('/api/_test/optional')->assertOk()
            ->assertJson(['authenticated' => true, 'id' => $user->id]);
    }

    public function test_inactive_user_token_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->as($user)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_cors_allows_credentials_for_the_frontend_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])->getJson('/api/_test/optional')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}

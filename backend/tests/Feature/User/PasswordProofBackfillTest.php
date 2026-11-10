<?php

namespace Tests\Feature\User;

use App\Auth\Factories\TokenFactory;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\Mail\ConfirmEmailChangeMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The migration adding `password_set_without_proof_at` stamps existing social accounts, failing closed. */
class PasswordProofBackfillTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_11_10_000001_add_password_set_without_proof_to_users_table.php');
        $this->migration->down();
    }

    private function socialUser(?string $password = null): User
    {
        $user = User::factory()->create($password === null ? [] : ['password' => $password]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g'.$user->id, 'access_token' => 'token']);

        return $user;
    }

    public function test_down_drops_the_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'password_set_without_proof_at'));
    }

    public function test_only_social_accounts_with_a_real_password_are_stamped(): void
    {
        config(['services.social_password.placeholder' => 'phpunit-placeholder-Xy9#only-for-tests']);
        $withRealPassword = $this->socialUser();
        $onPlaceholder = $this->socialUser(SocialPlaceholderPassword::forNewAccount());
        $passwordOnly = User::factory()->create();

        $this->migration->up();

        $this->assertNotNull($withRealPassword->refresh()->password_set_without_proof_at);
        $this->assertNull($onPlaceholder->refresh()->password_set_without_proof_at);
        $this->assertNull($passwordOnly->refresh()->password_set_without_proof_at);
    }

    public function test_without_a_configured_placeholder_every_social_account_is_stamped(): void
    {
        config(['services.social_password.placeholder' => null]);
        $socialUsers = [$this->socialUser(), $this->socialUser()];
        $passwordOnly = User::factory()->create();

        $this->migration->up();

        foreach ($socialUsers as $user) {
            $this->assertNotNull($user->refresh()->password_set_without_proof_at);
        }
        $this->assertNull($passwordOnly->refresh()->password_set_without_proof_at);
    }

    public function test_a_stamped_account_goes_through_current_address_approval(): void
    {
        Mail::fake();
        config(['services.social_password.placeholder' => 'phpunit-placeholder-Xy9#only-for-tests']);
        $user = $this->socialUser();
        $this->migration->up();

        $this->withHeader('Authorization', 'Bearer '.app(TokenFactory::class)->issueAccessToken($user->refresh())[0])
            ->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', true);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }
}

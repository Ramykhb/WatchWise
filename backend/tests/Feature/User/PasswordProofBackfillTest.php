<?php

namespace Tests\Feature\User;

use App\Auth\Factories\TokenFactory;
use App\Models\SocialAccount;
use App\Models\User;
use App\User\Mail\ConfirmEmailChangeMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The migration adding `password_set_without_proof_at` stamps existing social accounts, failing closed. */
class PasswordProofBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER = 'phpunit-placeholder-Xy9#only-for-tests';

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
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g'.$user->id]);

        return $user;
    }

    /** Registered with email and password, then verified through the emailed link an hour later. */
    private function passwordSignUp(): User
    {
        return User::factory()->create(['created_at' => now()->subHours(2), 'email_verified_at' => now()->subHour()]);
    }

    public function test_down_drops_the_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'password_set_without_proof_at'));
    }

    public function test_only_social_accounts_with_a_real_password_are_stamped(): void
    {
        $withRealPassword = $this->socialUser();
        $onPlaceholder = $this->socialUser(Hash::make(self::PLACEHOLDER));
        $passwordOnly = $this->passwordSignUp();

        $this->migration->upWithPlaceholder(self::PLACEHOLDER);

        $this->assertNotNull($withRealPassword->refresh()->password_set_without_proof_at);
        $this->assertNull($onPlaceholder->refresh()->password_set_without_proof_at);
        $this->assertNull($passwordOnly->refresh()->password_set_without_proof_at);
    }

    public function test_an_account_verified_at_creation_is_stamped_even_after_its_social_login_was_disconnected(): void
    {
        $disconnected = User::factory()->create(['created_at' => now()->subDay(), 'email_verified_at' => now()->subDay()->addMinute()]);
        $disconnectedOnPlaceholder = User::factory()->create(['password' => Hash::make(self::PLACEHOLDER), 'created_at' => now()->subDay(), 'email_verified_at' => now()->subDay()]);
        $neverVerified = User::factory()->unverified()->create();
        $passwordSignUp = $this->passwordSignUp();

        $this->migration->upWithPlaceholder(self::PLACEHOLDER);

        $this->assertNotNull($disconnected->refresh()->password_set_without_proof_at);
        $this->assertNull($disconnectedOnPlaceholder->refresh()->password_set_without_proof_at);
        $this->assertNull($neverVerified->refresh()->password_set_without_proof_at);
        $this->assertNull($passwordSignUp->refresh()->password_set_without_proof_at);
    }

    public function test_without_a_configured_placeholder_every_social_account_is_stamped(): void
    {
        $socialUsers = [$this->socialUser(), $this->socialUser()];
        $passwordOnly = $this->passwordSignUp();

        $this->migration->upWithPlaceholder(null);

        foreach ($socialUsers as $user) {
            $this->assertNotNull($user->refresh()->password_set_without_proof_at);
        }
        $this->assertNull($passwordOnly->refresh()->password_set_without_proof_at);
    }

    public function test_a_stamped_account_goes_through_current_address_approval(): void
    {
        Mail::fake();
        $user = $this->socialUser();
        $this->migration->upWithPlaceholder(self::PLACEHOLDER);

        $this->withHeader('Authorization', 'Bearer '.app(TokenFactory::class)->issueAccessToken($user->refresh())[0])
            ->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', true);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }
}

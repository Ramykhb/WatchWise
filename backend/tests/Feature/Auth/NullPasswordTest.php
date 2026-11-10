<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Accounts made by social sign-in store no password (`password` is NULL) until they set one. */
class NullPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_sign_in_refuses_an_account_without_a_password_like_an_unknown_email(): void
    {
        User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $unknown = $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'anything1'])->assertUnauthorized();

        // No hash is ever checked against a NULL password: the account is refused before that.
        Hash::spy();
        $noPassword = $this->postJson('/api/auth/login', ['email' => 'soc@example.com', 'password' => 'anything1']);

        $noPassword->assertUnauthorized()->assertExactJson($unknown->json());
        $this->assertNull($noPassword->getCookie('access_token', false));
        $this->assertNull($noPassword->getCookie('refresh_token', false));
        Hash::shouldNotHaveReceived('check');
    }

    public function test_a_password_reset_gives_an_account_without_a_password_its_first_one(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $record = new PasswordReset(['email' => 'soc@example.com', 'token' => Hash::make('goodtoken')]);
        $record->created_at = now();
        $record->save();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'soc@example.com', 'token' => 'goodtoken', 'password' => 'reset12345', 'password_confirmation' => 'reset12345',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('reset12345', $user->password));
        // The emailed token proved the inbox, so this password counts as proof.
        $this->assertNull($user->password_set_without_proof_at);
        $this->postJson('/api/auth/login', ['email' => 'soc@example.com', 'password' => 'reset12345'])->assertOk();
    }
}

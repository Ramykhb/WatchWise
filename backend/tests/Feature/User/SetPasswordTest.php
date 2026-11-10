<?php

namespace Tests\Feature\User;

use App\Models\RefreshToken;
use App\Models\User;
use App\User\DTOs\Requests\SetPasswordData;
use App\User\Exceptions\PasswordAlreadySet;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\Mail\PasswordAddedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Social\SocialTestCase;

/** PUT /api/me/password/set (first password of an account without one) and PUT /api/me/password (change only). */
class SetPasswordTest extends SocialTestCase
{
    private const NEW_PASSWORD = ['password' => 'mine12345', 'password_confirmation' => 'mine12345'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_an_account_without_a_password_sets_one_and_stays_signed_in_here_only(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $elsewhere = RefreshToken::create(['user_id' => $user->id, 'token_hash' => 'other-device', 'family_id' => 'f', 'expires_at' => now()->addDay()]);

        $response = $this->as($user)->putJson('/api/me/password/set', self::NEW_PASSWORD)
            ->assertOk()
            ->assertExactJson(['message' => 'Password updated. Other devices have been signed out.']);

        $this->assertNotNull($response->getCookie('access_token', false));
        $this->assertNotNull($response->getCookie('refresh_token', false));
        $this->assertNotNull($elsewhere->refresh()->revoked_at);

        $user->refresh();
        $this->assertTrue(Hash::check('mine12345', $user->password));
        $this->assertNotNull($user->password_set_without_proof_at);
        Mail::assertSent(PasswordAddedMail::class, fn (PasswordAddedMail $mail) => $mail->hasTo('soc@example.com'));
        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', true)->assertJsonPath('deletion_by_email', true);
        $this->postJson('/api/auth/login', ['email' => 'soc@example.com', 'password' => 'mine12345'])->assertOk();
    }

    public function test_an_account_with_a_password_cannot_set_another_one(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/password/set', self::NEW_PASSWORD)
            ->assertStatus(409)
            ->assertJsonPath('code', 'password_already_set')
            ->assertJsonStructure(['message', 'code']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Mail::assertNothingSent();
    }

    public function test_setting_a_password_twice_is_refused_the_second_time(): void
    {
        $user = User::factory()->withoutPassword()->create();

        $this->as($user)->putJson('/api/me/password/set', self::NEW_PASSWORD)->assertOk();
        $this->as($user)->putJson('/api/me/password/set', ['password' => 'other12345', 'password_confirmation' => 'other12345'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'password_already_set');

        $this->assertTrue(Hash::check('mine12345', $user->fresh()->password));
    }

    public function test_a_password_set_meanwhile_is_not_overwritten_by_an_out_of_date_model(): void
    {
        $user = User::factory()->withoutPassword()->create();
        // Another request set one after this model was loaded.
        User::whereKey($user->id)->update(['password' => Hash::make('other12345')]);
        $this->assertNull($user->password);

        try {
            app(AccountServiceInterface::class)->setPassword($user, new SetPasswordData('mine12345'), Request::create('/'));
            $this->fail('An out-of-date model set a second password.');
        } catch (PasswordAlreadySet) {
        }

        $this->assertTrue(Hash::check('other12345', $user->fresh()->password));
        $this->assertNull($user->fresh()->password_set_without_proof_at);
        Mail::assertNothingSent();
    }

    public function test_a_weak_or_unconfirmed_password_is_refused(): void
    {
        $user = User::factory()->withoutPassword()->create();

        $this->as($user)->putJson('/api/me/password/set', ['password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($user)->putJson('/api/me/password/set', ['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($user)->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($user)->putJson('/api/me/password/set', ['password' => "mine12345\0x", 'password_confirmation' => "mine12345\0x"])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertNull($user->fresh()->password);
    }

    public function test_it_needs_a_signed_in_member_with_a_verified_email(): void
    {
        $this->putJson('/api/me/password/set', self::NEW_PASSWORD)->assertUnauthorized();

        $unverified = User::factory()->withoutPassword()->unverified()->create();
        $this->as($unverified)->putJson('/api/me/password/set', self::NEW_PASSWORD)->assertForbidden();
        $this->assertNull($unverified->fresh()->password);
    }

    public function test_it_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->as($user)->putJson('/api/me/password/set', self::NEW_PASSWORD)->assertStatus(409);
        }

        $this->as($user)->putJson('/api/me/password/set', self::NEW_PASSWORD)->assertStatus(429);
    }

    public function test_changing_needs_an_existing_password(): void
    {
        $user = User::factory()->withoutPassword()->create();

        $this->as($user)->putJson('/api/me/password', ['current_password' => 'anything1'] + self::NEW_PASSWORD)
            ->assertStatus(409)
            ->assertJsonPath('code', 'no_password_set');

        $this->assertNull($user->fresh()->password);
    }

    public function test_no_new_password_may_contain_a_nul_byte(): void
    {
        $nul = ['password' => "mine12345\0x", 'password_confirmation' => "mine12345\0x"];
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/password', ['current_password' => 'password'] + $nul)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', ['name' => 'Jane', 'email' => 'jane@example.com'] + $nul)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => 'any'] + $nul)
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_changing_always_needs_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/password', self::NEW_PASSWORD)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

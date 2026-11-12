<?php

namespace Tests\Feature\Auth;

use App\Auth\Support\DummyPassword;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/** Accounts made by social sign-in store no password (`password` is NULL) until they set one. */
class NullPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_sign_in_refuses_an_account_without_a_password_like_an_unknown_email(): void
    {
        User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $unknown = $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'anything1'])->assertUnauthorized();
        $dummyHash = Cache::get('auth:dummy-password-hash:'.config('hashing.bcrypt.rounds'));
        $this->assertIsString($dummyHash);

        // The NULL password is never checked; the dummy hash is, so the refusal takes as long as a wrong password.
        Hash::spy();
        $noPassword = $this->postJson('/api/auth/login', ['email' => 'soc@example.com', 'password' => 'anything1']);

        $noPassword->assertUnauthorized()->assertExactJson($unknown->json());
        $this->assertNull($noPassword->getCookie('access_token', false));
        $this->assertNull($noPassword->getCookie('refresh_token', false));
        Hash::shouldHaveReceived('check')->once()->with('anything1', $dummyHash);
        Hash::shouldNotHaveReceived('make');
    }

    public function test_an_unknown_email_is_checked_against_the_dummy_hash_too(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'warmup@example.com', 'password' => 'anything1'])->assertUnauthorized();
        $dummyHash = Cache::get('auth:dummy-password-hash:'.config('hashing.bcrypt.rounds'));

        Hash::spy();
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'anything1'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials.']);

        Hash::shouldHaveReceived('check')->once()->with('anything1', $dummyHash);
    }

    public function test_sign_in_still_answers_when_the_cache_store_is_down(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret1234']);
        User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $fallback = new ReflectionProperty(DummyPassword::class, 'fallbackHashes');
        $fallback->setValue(null, []);
        Cache::partialMock()->shouldReceive('rememberForever')->andThrow(new RuntimeException('cache down'));

        // Nothing answers 500: the dummy hash is made in-process, once, and reused.
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'anything1'])->assertUnauthorized();
        $first = $fallback->getValue();
        $this->postJson('/api/auth/login', ['email' => 'soc@example.com', 'password' => 'anything1'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret1234'])->assertOk();

        $rounds = (int) config('hashing.bcrypt.rounds');
        $this->assertSame([$rounds], array_keys($first));
        $this->assertSame('bcrypt', Hash::info($first[$rounds])['algoName']);
        $this->assertSame($first, $fallback->getValue());
    }

    public function test_a_password_reset_gives_an_account_without_a_password_its_first_one(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);
        $record = new PasswordReset(['email' => 'soc@example.com', 'token' => hash('sha256', 'goodtoken')]);
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

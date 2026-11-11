<?php

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Social\SocialTestCase;

/**
 * Every endpoint where a member chooses a password (register, reset, set, change) applies the same rules
 * (`App\Support\NewPasswordRules`): 8+ characters with letters and numbers, at most 72 bytes, no NUL byte.
 * Sign-in only caps the length (1024 bytes) so older, longer passwords keep working.
 */
class NewPasswordRulesTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @return array<string, array{string}> */
    public static function endpoints(): array
    {
        return ['register' => ['register'], 'reset' => ['reset'], 'set' => ['set'], 'change' => ['change']];
    }

    /** @return array<string, array{string, string}> */
    public static function rejectedPasswords(): array
    {
        $cases = [
            'letters only' => 'aaaaaaaa',
            'digits only' => '12345678',
            '73 ASCII bytes' => str_repeat('a', 71).'12',
            '73 bytes in 38 characters' => str_repeat('é', 35).'a12',
            'a NUL byte' => "abcd1234\0xyz",
        ];

        $data = [];
        foreach (array_keys(self::endpoints()) as $endpoint) {
            foreach ($cases as $label => $password) {
                $data["{$endpoint}: {$label}"] = [$endpoint, $password];
            }
        }

        return $data;
    }

    private function choose(string $endpoint, string $password): TestResponse
    {
        $body = ['password' => $password, 'password_confirmation' => $password];

        return match ($endpoint) {
            'register' => $this->postJson('/api/auth/register', ['name' => 'Jane', 'email' => 'jane@example.com', ...$body]),
            'reset' => $this->postJson('/api/auth/reset-password', ['email' => 'jane@example.com', 'token' => 'unused', ...$body]),
            'set' => $this->as(User::factory()->withoutPassword()->create())->putJson('/api/me/password/set', $body),
            'change' => $this->as(User::factory()->create())->putJson('/api/me/password', ['current_password' => 'password', ...$body]),
        };
    }

    #[DataProvider('rejectedPasswords')]
    public function test_a_password_breaking_a_rule_is_rejected(string $endpoint, string $password): void
    {
        $this->choose($endpoint, $password)->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    #[DataProvider('endpoints')]
    public function test_a_72_byte_password_with_letters_and_numbers_passes_the_rules(string $endpoint): void
    {
        $this->choose($endpoint, str_repeat('é', 34).'ab12')->assertJsonMissingValidationErrors('password');
    }

    public function test_the_byte_limit_says_what_counts(): void
    {
        $this->choose('register', str_repeat('a', 71).'12')
            ->assertJsonPath('errors.password.0', 'The password must not be longer than 72 bytes (accented, non-Latin and emoji characters count as more than one).');
    }

    public function test_sign_in_caps_the_password_at_1024_bytes_without_hashing_it(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => str_repeat('a', 1025)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => str_repeat('a', 1024)])
            ->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\Exceptions\EmailAlreadyRegistered;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Registration: the display-name rule, and an account plus its first session created together or not at all. */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function register(string $name, string $email = 'jane@example.com'): TestResponse
    {
        return $this->postJson('/api/auth/register', [
            'name' => $name, 'email' => $email, 'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ]);
    }

    /** @return array<string, array{string}> */
    public static function badNames(): array
    {
        return [
            'only spaces' => ['   '],
            'a line break' => ["Tom\nClick here"],
            'a tab' => ["Tom\tO"],
            'a right-to-left override' => ["Tom\u{202E}gnp.exe"],
            'a right-to-left isolate' => ["Tom\u{2067}gnp"],
            'a left-to-right mark' => ["To\u{200E}m"],
            'a line separator' => ["Tom\u{2028}Click here"],
            'a byte order mark inside' => ["To\u{FEFF}m"],
            'a private-use character' => ["Tom\u{E000}"],
            'a NUL byte' => ["To\0m"],
            '256 characters' => [str_repeat('é', 256)],
        ];
    }

    #[DataProvider('badNames')]
    public function test_a_name_breaking_the_rule_is_rejected(string $name): void
    {
        $this->register($name)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_control_characters_get_a_clear_message(): void
    {
        $this->register("Tom\nClick here")
            ->assertJsonPath('errors.name.0', 'The name may not contain line breaks, tabs or invisible direction characters.');
    }

    public function test_emoji_sequences_joined_by_zwj_and_scripts_using_zwnj_are_accepted(): void
    {
        $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} Family";

        $this->register($family, 'a@example.com')->assertCreated()->assertJsonPath('user.name', $family);
        $this->register("\u{2764}\u{FE0F} Heart", 'b@example.com')->assertCreated();
        $this->register("می\u{200C}خواهم", 'c@example.com')->assertCreated();
    }

    public function test_names_in_any_script_with_accents_emoji_and_punctuation_are_accepted(): void
    {
        $this->register("Zoë O'Brien-Łukasz 🎬", 'a@example.com')->assertCreated();
        $this->register('محمد', 'b@example.com')->assertCreated();
        $this->register(str_repeat('é', 255), 'c@example.com')->assertCreated();
        $this->register('  Jo  ', 'd@example.com')->assertCreated()->assertJsonPath('user.name', 'Jo');
    }

    public function test_the_profile_form_applies_the_same_rule(): void
    {
        $user = User::factory()->create();
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        $this->withToken($jwt)->patchJson('/api/me', ['name' => "Tom\u{202E}gnp"])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_registration_that_lost_the_race_for_its_email_gets_the_validation_422(): void
    {
        // The winner committed after this request passed validation: the unique index decides.
        User::factory()->create(['email' => 'jane@example.com']);
        $validation = $this->register('Jane')->assertUnprocessable()->json();

        try {
            app(AuthServiceInterface::class)->register(new RegisterData('Jane', 'jane@example.com', 'secret1234'), Request::create('/'));
            $this->fail('The duplicate email must be refused.');
        } catch (EmailAlreadyRegistered $refused) {
            $response = $refused->render();
        }

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($validation, $response->getData(true));
        $this->assertSame('The email has already been taken.', $response->getData(true)['errors']['email'][0]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_a_username_collision_is_retried_once_with_a_fresh_username(): void
    {
        User::factory()->create(['username' => 'jane']);
        $collided = false;
        // A concurrent sign-up took the generated handle between the check and the insert.
        User::creating(function (User $user) use (&$collided): void {
            if (! $collided) {
                $collided = true;
                $user->username = 'jane';
            }
        });

        $this->register('Jane')->assertCreated()->assertJsonPath('user.username', 'jane2');
        $this->assertTrue($collided);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_no_account_is_left_behind_when_its_session_cannot_be_issued(): void
    {
        $this->withoutExceptionHandling();
        $this->mock(TokenFactory::class)->shouldReceive('issue')->andThrow(new RuntimeException('token store down'));

        try {
            $this->register('Jane');
            $this->fail('The failure must surface.');
        } catch (RuntimeException $failure) {
            $this->assertSame('token store down', $failure->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        Mail::assertNothingOutgoing();
    }
}

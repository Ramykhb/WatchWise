<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Social\SocialTestCase;

/** Handles are stored lowercase and looked up whatever case is typed. */
class LowercaseUsernameTest extends SocialTestCase
{
    public function test_a_mixed_case_handle_is_saved_lowercase(): void
    {
        $user = User::factory()->create();

        $this->as($user)->patchJson('/api/me', ['username' => '@Mali_Fan'])
            ->assertOk()
            ->assertJsonPath('user.username', 'mali_fan');

        $this->assertSame('mali_fan', DB::table('users')->where('id', $user->id)->value('username'));
    }

    public function test_a_handle_taken_in_another_case_is_refused(): void
    {
        // A handle stored before lowercasing, written around the model's mutator.
        $legacy = User::factory()->create();
        DB::table('users')->where('id', $legacy->id)->update(['username' => 'Mali']);
        $user = User::factory()->create();

        $this->as($user)->patchJson('/api/me', ['username' => 'mali'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
        $this->as($user)->patchJson('/api/me', ['username' => 'MALI'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
        $this->as($legacy)->patchJson('/api/me', ['username' => 'mali'])->assertOk()->assertJsonPath('user.username', 'mali');
    }

    public function test_handles_written_through_the_model_are_lowercase(): void
    {
        $user = User::factory()->create(['username' => 'MixedCase']);

        $this->assertSame('mixedcase', DB::table('users')->where('id', $user->id)->value('username'));
    }

    public function test_profiles_and_search_match_whatever_case_is_typed(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Someone', 'username' => 'mali']);

        $this->as($me)->getJson('/api/users/Mali')->assertOk()->assertJsonPath('user.username', 'mali');
        $this->as($me)->getJson('/api/users?q=@MALI')->assertOk()->assertJsonPath('users.0.username', 'mali');
    }

    public function test_the_migration_lowercases_handles_and_resolves_collisions_oldest_first(): void
    {
        $oldest = User::factory()->create(['created_at' => now()->subDays(3)]);
        $middle = User::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = User::factory()->create(['created_at' => now()->subDay()]);
        $holdsSuffix = User::factory()->create(['created_at' => now()]);
        $long = User::factory()->create(['created_at' => now()->subDays(5)]);
        $longLater = User::factory()->create(['created_at' => now()]);
        $unique = User::factory()->create();
        $set = fn (User $user, string $username) => DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        $set($oldest, 'Mali');
        $set($middle, 'mali');
        $set($newest, 'MALI');
        $set($holdsSuffix, 'mali2');
        $set($long, 'Abcdefghijklmnopqrst');
        $set($longLater, 'abcdefghijklmnopqrst');
        $set($unique, 'Solo');

        (require database_path('migrations/2026_11_11_000002_lowercase_usernames.php'))->up();

        $username = fn (User $user) => DB::table('users')->where('id', $user->id)->value('username');
        $this->assertSame('mali', $username($oldest));
        $this->assertSame('mali3', $username($middle));
        $this->assertSame('mali4', $username($newest));
        $this->assertSame('mali2', $username($holdsSuffix));
        $this->assertSame('abcdefghijklmnopqrst', $username($long));
        $this->assertSame('abcdefghijklmnopqrs2', $username($longLater));
        $this->assertSame('solo', $username($unique));
    }
}

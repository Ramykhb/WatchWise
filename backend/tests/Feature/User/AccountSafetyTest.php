<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\User\Mail\PasswordAddedMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Social\SocialTestCase;

/** Search input rules, password change sessions, first-password notice, avatar storage and small User rules. */
class AccountSafetyTest extends SocialTestCase
{
    /** A valid 1x1 PNG, so the image rule passes without the GD extension. */
    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('me.png', base64_decode(self::ONE_PIXEL_PNG));
    }

    private function cookieValue($response, string $name): ?string
    {
        return $response->getCookie($name, false)?->getValue();
    }

    private function refreshWith(string $token)
    {
        return $this->withCredentials()->withUnencryptedCookie('refresh_token', $token)->postJson('/api/auth/refresh');
    }

    public function test_a_non_text_search_term_is_treated_as_empty(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Lina Saad', 'username' => 'linasaad']);

        $this->as($me)->getJson('/api/users?q[]=lina')->assertOk()->assertJsonStructure(['users']);
        $this->as($me)->getJson('/api/users?q[a][b]=lina&limit[]=3')->assertOk();
    }

    public function test_a_very_long_search_term_is_cut_and_normal_search_still_works(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Lina Saad', 'username' => 'linasaad']);

        $this->as($me)->getJson('/api/users?q='.str_repeat('a', 5000).'&limit=999')->assertOk()->assertJsonPath('total', 0);
        $this->as($me)->getJson('/api/users?q=lina&limit=abc')->assertOk()->assertJsonPath('users.0.username', 'linasaad');
    }

    public function test_changing_the_password_keeps_this_device_signed_in_and_signs_out_the_others(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $here = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password']);
        $there = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password']);
        $hereRefresh = $this->cookieValue($here, 'refresh_token');
        $thereRefresh = $this->cookieValue($there, 'refresh_token');

        $changed = $this->withCredentials()->withUnencryptedCookie('access_token', $this->cookieValue($here, 'access_token'))
            ->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();

        $newAccess = $this->cookieValue($changed, 'access_token');
        $newRefresh = $this->cookieValue($changed, 'refresh_token');
        $this->assertNotNull($newAccess);
        $this->assertNotNull($newRefresh);

        $this->withCredentials()->withUnencryptedCookie('access_token', $newAccess)->getJson('/api/me')->assertOk();
        $this->refreshWith($newRefresh)->assertOk();
        $this->refreshWith($thereRefresh)->assertUnauthorized();
        $this->refreshWith($hereRefresh)->assertUnauthorized();
    }

    public function test_an_ordinary_password_change_sends_no_notice(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_setting_a_first_password_mails_a_notice(): void
    {
        Mail::fake();
        config(['services.frontend_url' => 'http://front.test']);
        $user = User::factory()->withoutPassword()->create(['email' => 'soc@example.com']);

        $this->as($user)->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])->assertOk();

        Mail::assertSent(PasswordAddedMail::class, fn (PasswordAddedMail $mail) => $mail->hasTo('soc@example.com'));
        $html = (new PasswordAddedMail($user->name))->render();
        $this->assertStringContainsString('http://front.test/forgot-password', $html);
        $this->assertStringContainsString('A password was added', $html);

        // Changing it again is an ordinary change: no second notice.
        $this->as($user)->putJson('/api/me/password', ['current_password' => 'mine12345', 'password' => 'other12345', 'password_confirmation' => 'other12345'])->assertOk();
        Mail::assertSent(PasswordAddedMail::class, 1);
    }

    public function test_an_uploaded_avatar_is_stored_as_a_path_and_served_as_a_full_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $url = $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()])->assertOk()->json('user.avatar');

        $stored = DB::table('users')->where('id', $user->id)->value('avatar');
        $this->assertMatchesRegularExpression('#^avatars/[^/]+\.png$#', $stored);
        $this->assertSame(Storage::disk('public')->url($stored), $url);
        $this->as($user)->getJson("/api/users/{$user->username}")->assertJsonPath('user.avatar', $url);
    }

    public function test_replacing_an_avatar_removes_the_old_file_only_after_the_new_one_is_saved(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()])->assertOk();
        $old = DB::table('users')->where('id', $user->id)->value('avatar');

        $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()])->assertOk();

        $new = DB::table('users')->where('id', $user->id)->value('avatar');
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_a_failed_database_update_keeps_the_old_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()])->assertOk();
        $old = DB::table('users')->where('id', $user->id)->value('avatar');

        User::saving(fn () => throw new \RuntimeException('database down'));
        $this->withoutExceptionHandling();

        try {
            $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()]);
            $this->fail('The update should have failed.');
        } catch (\RuntimeException) {
            Storage::disk('public')->assertExists($old);
            $this->assertCount(1, Storage::disk('public')->files('avatars'));
            $this->assertSame($old, DB::table('users')->where('id', $user->id)->value('avatar'));
        }
    }

    public function test_external_avatar_urls_are_left_alone(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'https://lh3.googleusercontent.com/a/pic=s96']);

        $this->as($user)->getJson('/api/me')->assertJsonPath('user.avatar', 'https://lh3.googleusercontent.com/a/pic=s96');

        $this->as($user)->postJson('/api/me/avatar', ['avatar' => $this->png()])->assertOk();
        $this->as($user)->deleteJson('/api/me/avatar')->assertOk()->assertJsonPath('user.avatar', null);
        $this->assertCount(0, Storage::disk('public')->files('avatars'));
    }

    public function test_the_migration_turns_old_upload_urls_into_paths_and_back(): void
    {
        $migration = require database_path('migrations/2026_11_10_075004_convert_user_avatars_to_storage_paths.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $uploaded = User::factory()->create();
        $google = User::factory()->create();
        $lookalike = User::factory()->create();
        DB::table('users')->where('id', $uploaded->id)->update(['avatar' => 'https://old.example.com/storage/avatars/abc.png']);
        DB::table('users')->where('id', $google->id)->update(['avatar' => 'https://lh3.googleusercontent.com/a/pic']);
        DB::table('users')->where('id', $lookalike->id)->update(['avatar' => 'https://cdn.example.com/storage/avatars/nested/abc.png']);

        $migration->up();

        $this->assertSame('avatars/abc.png', DB::table('users')->where('id', $uploaded->id)->value('avatar'));
        $this->assertSame('https://lh3.googleusercontent.com/a/pic', DB::table('users')->where('id', $google->id)->value('avatar'));
        $this->assertSame('https://cdn.example.com/storage/avatars/nested/abc.png', DB::table('users')->where('id', $lookalike->id)->value('avatar'));

        $migration->down();

        $this->assertSame(Storage::disk('public')->url('avatars/abc.png'), DB::table('users')->where('id', $uploaded->id)->value('avatar'));
    }

    public function test_a_hidden_follower_list_answers_json(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->as($owner)->patchJson('/api/me/preferences', ['privacy' => ['following' => false]])->assertOk();

        $this->as($viewer)->getJson("/api/users/{$owner->username}/followers")
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_favorite_genres_must_not_repeat(): void
    {
        $this->as(User::factory()->create())
            ->patchJson('/api/me/preferences', ['favorite_genres' => ['Drama', 'Drama']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['favorite_genres.0', 'favorite_genres.1']);
    }
}

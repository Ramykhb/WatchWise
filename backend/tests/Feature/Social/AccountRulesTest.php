<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AccountRulesTest extends SocialTestCase
{
    /** A valid 1x1 PNG, so the image rule passes without the GD extension. */
    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_deleting_the_account_needs_the_right_password(): void
    {
        $user = User::factory()->create();

        $this->as($user)->deleteJson('/api/me', ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'That password is incorrect.');
        $this->assertModelExists($user);

        $this->as($user)->deleteJson('/api/me', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted.');
        $this->assertModelMissing($user);
    }

    public function test_a_wrong_current_password_is_reported_on_that_field(): void
    {
        $this->as(User::factory()->create())
            ->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Your current password is incorrect.');
    }

    public function test_changing_the_email_marks_it_unverified(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->as($user)->patchJson('/api/me', ['email' => 'new.address@example.com'])->assertOk();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_avatar_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $url = $this->as($user)->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('me.png', base64_decode(self::ONE_PIXEL_PNG))])
            ->assertOk()
            ->json('user.avatar');
        $this->assertNotNull($url);
        $this->assertCount(1, Storage::disk('public')->files('avatars'));

        $this->as($user)->deleteJson('/api/me/avatar')->assertOk()->assertJsonPath('user.avatar', null);
        $this->assertCount(0, Storage::disk('public')->files('avatars'));
    }

    public function test_hidden_follower_lists_are_forbidden(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->as($owner)->patchJson('/api/me/preferences', ['privacy' => ['following' => false]])->assertOk();

        $this->as($viewer)->getJson("/api/users/{$owner->username}/followers")->assertForbidden();
        $this->as($viewer)->getJson("/api/users/{$owner->username}/following")->assertForbidden();
        $this->as($owner)->getJson("/api/users/{$owner->username}/followers")->assertOk();
    }

    public function test_home_has_the_expected_shape(): void
    {
        $this->as(User::factory()->create())->getJson('/api/home')
            ->assertOk()
            ->assertJsonStructure(['activity', 'rooms', 'next_night'])
            ->assertJsonPath('next_night', null);
    }
}

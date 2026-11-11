<?php

namespace Tests\Feature\Auth\Repository;

use App\Auth\Repository\UserRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_and_email(): void
    {
        $user = User::factory()->create();
        $repo = new UserRepository;

        $this->assertTrue($repo->findById($user->id)->is($user));
        $this->assertTrue($repo->findByEmail($user->email)->is($user));
        $this->assertNull($repo->findById(999999));
        $this->assertNull($repo->findByEmail('nobody@example.com'));
    }

    public function test_create_hashes_password_and_returns_refreshed_model(): void
    {
        $user = (new UserRepository)->create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret-pass-1']);

        $this->assertDatabaseHas('users', ['email' => 'ann@example.com']);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        $this->assertNotNull($user->id);
    }

    public function test_mark_email_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        (new UserRepository)->markEmailVerified($user);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_clear_avatar_removes_the_photo(): void
    {
        $user = User::factory()->create(['avatar' => 'https://example.com/me.png']);

        (new UserRepository)->clearAvatar($user);

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_rehash_writes_only_while_the_verified_hash_is_still_stored(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);
        $verified = $user->password;
        $repo = new UserRepository;

        // A reset committed between the login's check and its upgrade: the upgrade must not bring the old password back.
        User::query()->whereKey($user->id)->update(['password' => Hash::make('changed999')]);
        $this->assertFalse($repo->rehashPassword($user, $verified, 'secret1234'));
        $this->assertTrue(Hash::check('changed999', $user->fresh()->password));
        $this->assertSame($verified, $user->password);

        $fresh = $user->fresh();
        $current = $fresh->password;
        $this->assertTrue($repo->rehashPassword($fresh, $current, 'changed999'));
        $stored = User::query()->whereKey($user->id)->value('password');
        $this->assertNotSame($current, $stored);
        $this->assertSame($stored, $fresh->password);
        $this->assertTrue(Hash::check('changed999', $fresh->password));
    }

    public function test_update_password_hashes(): void
    {
        $user = User::factory()->create();

        (new UserRepository)->updatePassword($user, 'brand-new-pass');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_clear_password_leaves_no_password_and_no_unproven_mark(): void
    {
        $user = User::factory()->create(['password_set_without_proof_at' => now()]);

        (new UserRepository)->clearPassword($user);

        $user = $user->fresh();
        $this->assertNull($user->password);
        $this->assertNull($user->password_set_without_proof_at);
        $this->assertFalse($user->hasPassword());
    }
}

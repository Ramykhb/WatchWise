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

    public function test_update_password_hashes(): void
    {
        $user = User::factory()->create();

        (new UserRepository)->updatePassword($user, 'brand-new-pass');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }
}

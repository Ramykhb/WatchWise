<?php

namespace Tests\Feature\Auth\Repository;

use App\Auth\Repository\SocialAccountRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_find_by_provider(): void
    {
        $user = User::factory()->create();
        $repo = new SocialAccountRepository;

        $account = $repo->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123',
            'access_token' => 'tok',
        ]);

        $found = $repo->findByProvider('google', '123');
        $this->assertTrue($found->is($account));
        $this->assertSame('tok', $found->access_token);
        $this->assertNull($repo->findByProvider('github', '123'));
        $this->assertNull($repo->findByProvider('google', '999'));
    }

    public function test_update_access_token(): void
    {
        $user = User::factory()->create();
        $repo = new SocialAccountRepository;
        $account = $repo->create(['user_id' => $user->id, 'provider' => 'github', 'provider_id' => '7']);

        $repo->updateAccessToken($account, 'new-token');
        $this->assertSame('new-token', $account->fresh()->access_token);

        $repo->updateAccessToken($account, null);
        $this->assertNull($account->fresh()->access_token);
    }
}

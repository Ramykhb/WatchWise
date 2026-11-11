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
        ]);

        $found = $repo->findByProvider('google', '123');
        $this->assertTrue($found->is($account));
        $this->assertNull($found->getRawOriginal('access_token'));
        $this->assertNull($repo->findByProvider('github', '123'));
        $this->assertNull($repo->findByProvider('google', '999'));
    }
}

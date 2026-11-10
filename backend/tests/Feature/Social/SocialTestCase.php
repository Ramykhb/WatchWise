<?php

namespace Tests\Feature\Social;

use App\Auth\Factories\TokenFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SocialTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Signs in as the user now: the token carries the stored session version, so it still works after a step
     * of the test revoked the user's earlier sessions (the in-memory model may hold an older version).
     */
    protected function as(User $user): static
    {
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user, (int) User::whereKey($user->id)->value('session_version'));

        return $this->withHeader('Authorization', 'Bearer '.$jwt);
    }
}

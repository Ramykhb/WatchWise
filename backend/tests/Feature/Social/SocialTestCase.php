<?php

namespace Tests\Feature\Social;

use App\Auth\Factories\TokenFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SocialTestCase extends TestCase
{
    use RefreshDatabase;

    protected function as(User $user): static
    {
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        return $this->withHeader('Authorization', 'Bearer '.$jwt);
    }
}

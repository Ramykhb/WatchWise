<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Responses\UserResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_shape_matches_the_model_serialization(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertEquals($user->toArray(), UserResponse::fromModel($user)->jsonSerialize());
    }
}

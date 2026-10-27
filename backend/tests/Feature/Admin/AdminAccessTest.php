<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class AdminAccessTest extends AdminTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'stats' => ['getJson', '/api/admin/stats'],
            'list users' => ['getJson', '/api/admin/users'],
            'update user' => ['patchJson', '/api/admin/users/1'],
            'delete user' => ['deleteJson', '/api/admin/users/1'],
            'list reviews' => ['getJson', '/api/admin/reviews'],
            'moderate review' => ['patchJson', '/api/admin/reviews/1'],
            'delete review' => ['deleteJson', '/api/admin/reviews/1'],
            'list movies' => ['getJson', '/api/admin/movies'],
            'update movie' => ['patchJson', '/api/admin/movies/550'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_unauthenticated(string $method, string $uri): void
    {
        $this->$method($uri)->assertUnauthorized();
    }

    #[DataProvider('adminRoutes')]
    public function test_members_get_forbidden(string $method, string $uri): void
    {
        $member = User::factory()->create();
        $this->review();
        LocalMovie::factory()->create(['tmdb_id' => 550]);

        $this->as($member)->$method($uri)->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Movie\Services\MovieCatalogService;
use Tests\Feature\Social\SocialTestCase;

abstract class AdminTestCase extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(MovieCatalogServiceInterface::class, MovieCatalogService::class);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function review(array $attributes = []): Review
    {
        return Review::create([
            'user_id' => User::factory()->create()->id,
            'tmdb_id' => 550,
            'rating' => 4,
            'content' => 'Great twist.',
            'sentiment' => 'positive',
            'is_approved' => false,
            ...$attributes,
        ]);
    }
}

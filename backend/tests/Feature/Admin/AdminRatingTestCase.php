<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use Carbon\CarbonInterface;

abstract class AdminRatingTestCase extends AdminTestCase
{
    protected function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /** A star rating: a `reviews` row without text. */
    protected function rating(array $attributes = [], ?CarbonInterface $createdAt = null): Review
    {
        $rating = Review::create([
            'user_id' => User::factory()->create()->id,
            'tmdb_id' => 550,
            'media_type' => 'movie',
            'rating' => 4,
            'content' => null,
            'sentiment' => null,
            'is_approved' => true,
            ...$attributes,
        ]);

        if ($createdAt !== null) {
            $rating->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $rating;
    }
}

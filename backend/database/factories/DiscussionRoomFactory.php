<?php

namespace Database\Factories;

use App\Models\DiscussionRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionRoom>
 */
class DiscussionRoomFactory extends Factory
{
    protected $model = DiscussionRoom::class;

    /**
     * An open, public room about a movie; it has no members yet (the creator is not added to `room_members`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tmdb_id' => fake()->numberBetween(1, 999_999),
            'media_type' => 'movie',
            'name' => fake()->words(3, true),
            'created_by' => User::factory(),
            'spoilers' => false,
            'is_private' => false,
            'is_locked' => false,
        ];
    }

    public function private(): static
    {
        return $this->state(['is_private' => true]);
    }

    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}

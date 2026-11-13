<?php

namespace Database\Factories;

use App\Models\AdminUserNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminUserNote>
 */
class AdminUserNoteFactory extends Factory
{
    protected $model = AdminUserNote::class;

    /**
     * A note by an admin about a member; use `by()` to pick the author and pass `user_id` for the member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'author_id' => User::factory()->admin(),
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }

    public function by(User $author): static
    {
        return $this->state(['author_id' => $author->id]);
    }

    public function about(User $member): static
    {
        return $this->state(['user_id' => $member->id]);
    }
}
